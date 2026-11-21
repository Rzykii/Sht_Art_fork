<?php
/**
 * SHT — Panel de administración
 * Secciones: 01 Catálogo (productos) · 02 Portafolio (dibujos) · 03 Pedidos
 *
 * Convención de imágenes: cada archivo subido lleva el ID de su registro en el nombre
 * y se guarda en una carpeta propia:
 *   - Dibujos   → uploads/artworks/obra-{id}-{titulo}-{hash}.{ext}
 *   - Productos → uploads/products/producto-{id}-{hash}.{ext}
 */

require 'config/database.php';
require 'includes/auth.php';
require 'includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
require_admin();

/* ==========================================================================
   Constantes
   ========================================================================== */

const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
const ARTWORK_MAX_BYTES = 8 * 1024 * 1024;
const PRODUCT_MAX_BYTES = 5 * 1024 * 1024;
const ARTWORK_TITLE_MAX = 100;          // coincide con dibujos.titulo VARCHAR(100)
const ARTWORKS_DIR = __DIR__ . '/uploads/artworks';
const PRODUCTS_DIR = __DIR__ . '/uploads/products';
const ORDER_STATUSES = ['pendiente', 'pagado', 'preparando', 'enviado', 'entregado', 'cancelado'];
const PRODUCT_CATEGORIES = ['Obra original', 'Print', 'Stickers', 'Pines', 'Otros'];
const DEFAULT_PRODUCT_IMAGE = 'images/demo/obra-01.svg';

/* ==========================================================================
   Utilidades
   ========================================================================== */

/** Valida un archivo subido y devuelve su tipo MIME. Lanza RuntimeException si no es válido. */
function validate_image_upload(array $file, int $maxBytes): string
{
    $maxMb = (int)($maxBytes / 1024 / 1024);
    if (
        $file['error'] !== UPLOAD_ERR_OK
        || $file['size'] < 1
        || $file['size'] > $maxBytes
        || !is_uploaded_file($file['tmp_name'])
    ) {
        throw new RuntimeException("El archivo debe ser una imagen de máximo {$maxMb} MB.");
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime])) {
        throw new RuntimeException('Usa JPG, PNG, WEBP o GIF.');
    }
    return $mime;
}

/** Crea la carpeta si no existe. */
function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    }
}

/** Convierte un título en un texto seguro para nombre de archivo. */
function slugify(string $text): string
{
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : $text;
    if ($ascii === false) $ascii = $text;
    $slug = trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii)), '-');
    return substr($slug ?: 'dibujo', 0, 48);
}

/** Ruta en disco de la imagen de un producto subida desde el panel (o null si es una imagen de demo). */
function product_storage_path(string $stored): ?string
{
    if (strpos($stored, 'uploads/products/') !== 0) return null;
    $url = product_public_url($stored);
    if (strpos($url, 'uploads/products/') !== 0) return null;
    return PRODUCTS_DIR . '/' . basename(rawurldecode($url));
}

/** Borra un archivo si existe. */
function remove_file(?string $path): void
{
    if ($path !== null && is_file($path)) unlink($path);
}

/* ==========================================================================
   Acciones: dibujos (portafolio)
   ========================================================================== */

function action_save_artwork(PDO $pdo): string
{
    $id          = max(0, (int)($_POST['artwork_id'] ?? 0));
    $title       = trim((string)($_POST['titulo'] ?? ''));
    $description = trim((string)($_POST['descripcion_obra'] ?? ''));

    if ($title === '' || mb_strlen($title) > ARTWORK_TITLE_MAX) {
        throw new RuntimeException('Indica un título de hasta ' . ARTWORK_TITLE_MAX . ' caracteres.');
    }

    // Al editar, se lee el archivo actual para poder reemplazarlo.
    $oldFile = null;
    if ($id) {
        $q = $pdo->prepare('SELECT archivo FROM dibujos WHERE id=?');
        $q->execute([$id]);
        $oldFile = $q->fetchColumn();
        if ($oldFile === false) throw new RuntimeException('El dibujo ya no existe.');
    }

    $hasFile = isset($_FILES['archivo_obra']) && ($_FILES['archivo_obra']['name'] ?? '') !== '';
    if (!$id && !$hasFile) throw new RuntimeException('Selecciona una imagen para el dibujo.');

    $file = $hasFile ? $_FILES['archivo_obra'] : null;
    $mime = $hasFile ? validate_image_upload($file, ARTWORK_MAX_BYTES) : null;

    $newAbsolute = null;
    $storedFile  = $oldFile;
    $pdo->beginTransaction();
    try {
        // Un dibujo nuevo se inserta primero para obtener su ID.
        if (!$id) {
            $pdo->prepare('INSERT INTO dibujos(titulo,descripcion,archivo,subido_por) VALUES(?,?,?,?)')
                ->execute([$title, $description, 'pendiente', (int)$_SESSION['user_id']]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($hasFile) {
            ensure_dir(ARTWORKS_DIR);
            $newName     = 'obra-' . $id . '-' . slugify($title) . '-' . bin2hex(random_bytes(6)) . '.' . IMAGE_TYPES[$mime];
            $newAbsolute = ARTWORKS_DIR . '/' . $newName;
            if (!move_uploaded_file($file['tmp_name'], $newAbsolute)) {
                throw new RuntimeException('No se pudo guardar la imagen.');
            }
            $storedFile = 'artworks/' . $newName;
        }

        $pdo->prepare('UPDATE dibujos SET titulo=?,descripcion=?,archivo=? WHERE id=?')
            ->execute([$title, $description, $storedFile, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        remove_file($newAbsolute);
        throw $e;
    }

    // El archivo anterior se borra solo después de confirmar el cambio en la base de datos.
    if ($hasFile && $oldFile !== null && $oldFile !== $storedFile) {
        remove_file(artwork_storage_path((string)$oldFile));
    }

    return 'Dibujo #' . $id . ' guardado. Visible en la galería de inicio.';
}

function action_delete_artwork(PDO $pdo): string
{
    $id = (int)($_POST['artwork_id'] ?? 0);
    $q  = $pdo->prepare('SELECT archivo FROM dibujos WHERE id=?');
    $q->execute([$id]);
    $file = $q->fetchColumn();
    if (!$file) throw new RuntimeException('El dibujo no existe.');

    $pdo->prepare('DELETE FROM dibujos WHERE id=?')->execute([$id]);
    remove_file(artwork_storage_path((string)$file));

    return 'Dibujo eliminado de la galería.';
}

/* ==========================================================================
   Acciones: productos (tienda)
   ========================================================================== */

function action_save_product(PDO $pdo): string
{
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim((string)($_POST['nombre'] ?? ''));
    $desc  = trim((string)($_POST['descripcion'] ?? ''));
    $cat   = trim((string)($_POST['categoria'] ?? 'Print'));
    $price = (int)($_POST['precio'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($name === '' || $price < 1 || $stock < 0) {
        throw new RuntimeException('Revisa nombre, precio y stock.');
    }
    if (!in_array($cat, PRODUCT_CATEGORIES, true)) $cat = 'Otros';

    // La imagen actual se toma de la base de datos, no del campo oculto del formulario.
    $oldImage = DEFAULT_PRODUCT_IMAGE;
    if ($id) {
        $q = $pdo->prepare('SELECT imagen FROM productos WHERE id=?');
        $q->execute([$id]);
        $oldImage = $q->fetchColumn();
        if ($oldImage === false) throw new RuntimeException('El producto ya no existe.');
    }

    $hasFile = !empty($_FILES['imagen']['name']);
    $file    = $hasFile ? $_FILES['imagen'] : null;
    $mime    = $hasFile ? validate_image_upload($file, PRODUCT_MAX_BYTES) : null;

    $newAbsolute = null;
    $pdo->beginTransaction();
    try {
        if ($id) {
            $pdo->prepare('UPDATE productos SET nombre=?,descripcion=?,categoria=?,precio=?,stock=? WHERE id=?')
                ->execute([$name, $desc, $cat, $price, $stock, $id]);
        } else {
            $pdo->prepare('INSERT INTO productos(nombre,descripcion,categoria,precio,stock,imagen) VALUES(?,?,?,?,?,?)')
                ->execute([$name, $desc, $cat, $price, $stock, $oldImage]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($hasFile) {
            ensure_dir(PRODUCTS_DIR);
            $filename    = 'producto-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . IMAGE_TYPES[$mime];
            $newAbsolute = PRODUCTS_DIR . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $newAbsolute)) {
                throw new RuntimeException('No se pudo guardar la imagen.');
            }
            $pdo->prepare('UPDATE productos SET imagen=? WHERE id=?')
                ->execute(['uploads/products/' . $filename, $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        remove_file($newAbsolute);
        throw $e;
    }

    if ($hasFile) remove_file(product_storage_path((string)$oldImage));

    return 'Producto #' . $id . ' guardado correctamente.';
}

function action_toggle_product(PDO $pdo): string
{
    $pdo->prepare('UPDATE productos SET activo=1-activo WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    return 'Estado del producto actualizado.';
}

function action_delete_product(PDO $pdo): string
{
    $id = (int)($_POST['id'] ?? 0);
    $q  = $pdo->prepare('SELECT imagen FROM productos WHERE id=?');
    $q->execute([$id]);
    $image = $q->fetchColumn();

    $pdo->prepare('DELETE FROM productos WHERE id=?')->execute([$id]);
    if ($image) remove_file(product_storage_path((string)$image));

    return 'Producto eliminado.';
}

/* ==========================================================================
   Acciones: pedidos
   ========================================================================== */

function action_order_status(PDO $pdo): string
{
    $status = $_POST['estado'] ?? '';
    if (!in_array($status, ORDER_STATUSES, true)) throw new RuntimeException('Estado no válido.');

    $pdo->prepare('UPDATE pedidos SET estado=? WHERE id=?')->execute([$status, (int)($_POST['id'] ?? 0)]);
    return 'Pedido actualizado.';
}

/* ==========================================================================
   Procesamiento del formulario (POST)
   ========================================================================== */

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $actions = [
        'save_artwork'   => 'action_save_artwork',
        'delete_artwork' => 'action_delete_artwork',
        'save_product'   => 'action_save_product',
        'toggle_product' => 'action_toggle_product',
        'delete_product' => 'action_delete_product',
        'order_status'   => 'action_order_status',
    ];
    $action = $_POST['action'] ?? '';

    try {
        if (isset($actions[$action])) {
            $msg = $actions[$action]($pdo);
        }
    } catch (Throwable $e) {
        $err = $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo completar la operación.';
    }
}

/* ==========================================================================
   Datos para la vista
   ========================================================================== */

// Registro en edición (si se llegó con ?edit= o ?edit_artwork=)
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM productos WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

$edit_artwork = null;
if (isset($_GET['edit_artwork'])) {
    $s = $pdo->prepare('SELECT * FROM dibujos WHERE id=?');
    $s->execute([(int)$_GET['edit_artwork']]);
    $edit_artwork = $s->fetch() ?: null;
}

$products = $pdo->query('SELECT * FROM productos ORDER BY created_at DESC')->fetchAll();
$artworks = $pdo->query('SELECT * FROM dibujos ORDER BY created_at DESC')->fetchAll();
$orders   = $pdo->query('SELECT * FROM pedidos ORDER BY created_at DESC LIMIT 100')->fetchAll();

$stats = [
    'products' => (int)$pdo->query('SELECT COUNT(*) FROM productos WHERE activo=1')->fetchColumn(),
    'orders'   => (int)$pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn(),
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='pendiente'")->fetchColumn(),
    'sales'    => (int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado IN ('pagado','preparando','enviado','entregado')")->fetchColumn(),
];
?>
<!doctype html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administración — SHT</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tienda.css">
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>

    <!-- ===== Cabecera ===== -->
    <header class="site-header admin-header">
        <a class="brand" href="index.php">
            <span class="brand-mark">S</span><span>SHT<span class="brand-dot">.</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="navegacion-principal">☰</button>
        <nav class="main-nav" id="navegacion-principal" aria-label="Navegación de administración">
            <a href="index.php">Ver portafolio</a>
            <a href="tienda.php">Ver tienda</a>
            <a href="cambiar_password.php">Cambiar contraseña</a>
        </nav>
        <div class="header-actions">
            <span class="admin-user"><?= h($_SESSION['username']) ?></span>
            <a class="login-btn" href="logout.php">Cerrar sesión</a>
        </div>
    </header>

    <main class="admin-wrap">

        <!-- ===== Título y mensajes ===== -->
        <div class="admin-title">
            <div>
                <p class="eyebrow">GESTIÓN DE LA TIENDA</p>
                <h1>Panel de <em>administración</em></h1>
                <p class="admin-sub">Administra el catálogo, controla el inventario y actualiza los pedidos.</p>
            </div>
        </div>

        <?php if ($msg): ?><p class="admin-alert success"><?= h($msg) ?></p><?php endif; ?>
        <?php if ($err): ?><p class="admin-alert error"><?= h($err) ?></p><?php endif; ?>

        <!-- ===== Estadísticas ===== -->
        <section class="stats-grid">
            <article class="stat"><small>Productos activos</small><strong><?= $stats['products'] ?></strong></article>
            <article class="stat"><small>Dibujos publicados</small><strong><?= count($artworks) ?></strong></article>
            <article class="stat"><small>Pedidos recibidos</small><strong><?= $stats['orders'] ?></strong></article>
            <article class="stat"><small>Pedidos pendientes</small><strong><?= $stats['pending'] ?></strong></article>
            <article class="stat"><small>Ventas confirmadas</small><strong><?= clp($stats['sales']) ?></strong></article>
        </section>

        <!-- ===== 01 · Catálogo de la tienda ===== -->
        <section class="admin-section" id="productos">
            <div class="admin-section-head">
                <div>
                    <p class="eyebrow">01 — CATÁLOGO DE LA TIENDA</p>
                    <h2><?= $edit ? 'Editar producto #' . (int)$edit['id'] : 'Agregar producto' ?></h2>
                </div>
                <a class="text-link" href="admin.php#productos">Nuevo producto ↗</a>
            </div>

            <form class="product-form" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" value="<?= h($edit['id'] ?? 0) ?>">

                <label>Nombre
                    <input name="nombre" required maxlength="140" value="<?= h($edit['nombre'] ?? '') ?>">
                </label>

                <label>Categoría
                    <select name="categoria">
                        <?php foreach (PRODUCT_CATEGORIES as $c): ?>
                            <option <?= ($edit['categoria'] ?? 'Print') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Precio (CLP)
                    <input name="precio" type="number" min="1" step="1" required value="<?= h($edit['precio'] ?? '') ?>">
                </label>

                <label>Stock disponible
                    <input name="stock" type="number" min="0" required value="<?= h($edit['stock'] ?? 0) ?>">
                </label>

                <label class="wide">Descripción
                    <textarea name="descripcion" rows="3"><?= h($edit['descripcion'] ?? '') ?></textarea>
                </label>

                <label class="wide">Imagen del producto
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small>Opcional. JPG, PNG, WEBP o GIF, máximo 5 MB. Se guarda en uploads/products/ con el ID del producto. Si no seleccionas una imagen se conserva la actual.</small>
                </label>

                <?php if ($edit): ?>
                    <div class="wide">
                        <small>Imagen actual:</small><br>
                        <img class="artwork-preview" src="<?= h(product_public_url((string)$edit['imagen'])) ?>" alt="Imagen actual del producto">
                    </div>
                <?php endif; ?>

                <div class="wide form-actions">
                    <button class="button button-primary" type="submit">Guardar producto</button>
                    <?php if ($edit): ?><a class="text-link" href="admin.php#productos">Cancelar edición</a><?php endif; ?>
                </div>
            </form>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$products): ?>
                            <tr><td colspan="7">Todavía no hay productos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>#<?= (int)$p['id'] ?></td>
                                <td>
                                    <div class="product-cell">
                                        <img src="<?= h(product_public_url((string)$p['imagen'])) ?>" alt="">
                                        <span><?= h($p['nombre']) ?></span>
                                    </div>
                                </td>
                                <td><?= h($p['categoria']) ?></td>
                                <td><?= clp($p['precio']) ?></td>
                                <td><?= (int)$p['stock'] ?></td>
                                <td><?= $p['activo'] ? 'Activo' : 'Oculto' ?></td>
                                <td class="actions">
                                    <a href="admin.php?edit=<?= (int)$p['id'] ?>#productos">Editar</a>

                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_product">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button><?= $p['activo'] ? 'Ocultar' : 'Publicar' ?></button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('¿Eliminar este producto?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button class="danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== 02 · Portafolio (dibujos de la portada) ===== -->
        <section class="admin-section" id="dibujos">
            <div class="admin-section-head">
                <div>
                    <p class="eyebrow">02 — PORTAFOLIO</p>
                    <h2><?= $edit_artwork ? 'Editar dibujo #' . (int)$edit_artwork['id'] : 'Agregar dibujo a la portada' ?></h2>
                    <p class="admin-sub">Los dibujos publicados aquí aparecen automáticamente en la galería de la página de inicio.</p>
                </div>
                <a class="text-link" href="admin.php#dibujos">Nuevo dibujo ↗</a>
            </div>

            <form class="product-form" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_artwork">
                <input type="hidden" name="artwork_id" value="<?= h($edit_artwork['id'] ?? 0) ?>">

                <label class="wide">Título del dibujo
                    <input name="titulo" required maxlength="<?= ARTWORK_TITLE_MAX ?>" value="<?= h($edit_artwork['titulo'] ?? '') ?>">
                </label>

                <label class="wide">Descripción
                    <textarea name="descripcion_obra" rows="3"><?= h($edit_artwork['descripcion'] ?? '') ?></textarea>
                </label>

                <label class="wide">Imagen del dibujo
                    <input type="file" name="archivo_obra" accept="image/jpeg,image/png,image/webp,image/gif" <?= $edit_artwork ? '' : 'required' ?>>
                    <small>JPG, PNG, WEBP o GIF, máximo 8 MB. Se guarda en uploads/artworks/ con el ID del dibujo. Al editar, puedes dejarlo vacío para conservar la imagen actual.</small>
                </label>

                <?php if ($edit_artwork): ?>
                    <div class="wide">
                        <small>Imagen actual:</small><br>
                        <img class="artwork-preview" src="<?= h(artwork_public_url((string)$edit_artwork['archivo'])) ?>" alt="Imagen actual del dibujo">
                    </div>
                <?php endif; ?>

                <div class="wide form-actions">
                    <button class="button button-primary" type="submit">Guardar dibujo</button>
                    <?php if ($edit_artwork): ?><a class="text-link" href="admin.php#dibujos">Cancelar edición</a><?php endif; ?>
                </div>
            </form>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Dibujo</th>
                            <th>Descripción</th>
                            <th>Fecha de publicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$artworks): ?>
                            <tr><td colspan="5">Todavía no hay dibujos publicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($artworks as $a): ?>
                            <tr>
                                <td>#<?= (int)$a['id'] ?></td>
                                <td>
                                    <div class="product-cell">
                                        <img src="<?= h(artwork_public_url((string)$a['archivo'])) ?>" alt="">
                                        <span><?= h($a['titulo']) ?></span>
                                    </div>
                                </td>
                                <td><?= h($a['descripcion']) ?></td>
                                <td><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                                <td class="actions">
                                    <a href="admin.php?edit_artwork=<?= (int)$a['id'] ?>#dibujos">Editar</a>

                                    <form method="post" onsubmit="return confirm('¿Eliminar este dibujo de la página de inicio?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_artwork">
                                        <input type="hidden" name="artwork_id" value="<?= (int)$a['id'] ?>">
                                        <button class="danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== 03 · Pedidos ===== -->
        <section class="admin-section" id="pedidos">
            <div class="admin-section-head">
                <div>
                    <p class="eyebrow">03 — VENTAS</p>
                    <h2>Pedidos recibidos</h2>
                </div>
            </div>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>N.º</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Pago</th>
                            <th>Estado / actualizar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$orders): ?>
                            <tr><td colspan="6">Todavía no hay pedidos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td>#<?= (int)$o['id'] ?></td>
                                <td>
                                    <?= h($o['nombre_cliente']) ?>
                                    <small class="cell-sub"><?= h($o['email']) ?></small>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                                <td><?= clp($o['total']) ?></td>
                                <td><?= h($o['metodo_pago']) ?></td>
                                <td>
                                    <form class="status-form" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="order_status">
                                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                        <select name="estado">
                                            <?php foreach (ORDER_STATUSES as $st): ?>
                                                <option value="<?= h($st) ?>" <?= $o['estado'] === $st ? 'selected' : '' ?>><?= h(ucfirst($st)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- ===== Pie de página ===== -->
    <footer class="site-footer">
        <a class="brand" href="index.php"><span class="brand-mark">S</span><span>SHT.</span></a>
        <p>Administración SHT.</p>
        <a class="back-top" href="#">VOLVER ARRIBA ↑</a>
    </footer>

    <script src="js/app.js"></script>
</body>

</html>