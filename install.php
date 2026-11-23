<?php
require 'config/database.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL,
 password VARCHAR(255) NOT NULL, rol ENUM('admin','usuario') DEFAULT 'usuario',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS dibujos (
 id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(100) NOT NULL, descripcion TEXT,
 archivo VARCHAR(255) NOT NULL, subido_por INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS productos (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(140) NOT NULL, descripcion TEXT NULL,
 categoria VARCHAR(60) NOT NULL DEFAULT 'Print', precio INT UNSIGNED NOT NULL,
 stock INT UNSIGNED NOT NULL DEFAULT 0, imagen VARCHAR(255) NOT NULL DEFAULT 'images/demo/obra-01.svg',
 activo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pedidos (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre_cliente VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL, telefono VARCHAR(40) NULL, direccion TEXT NOT NULL,
 notas TEXT NULL, total INT UNSIGNED NOT NULL, metodo_pago VARCHAR(40) NOT NULL DEFAULT 'Transferencia',
 estado ENUM('pendiente','pagado','preparando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS detalle_pedido (
 id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, producto_id INT NULL,
 nombre_producto VARCHAR(140) NOT NULL, cantidad INT UNSIGNED NOT NULL,
 precio_unitario INT UNSIGNED NOT NULL,
 FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
 FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pagos (
 id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL,
 proveedor VARCHAR(50) NOT NULL, referencia VARCHAR(190) NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'pendiente', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
 id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, category VARCHAR(80) NOT NULL DEFAULT 'Novedades', excerpt VARCHAR(500) NULL,
 content LONGTEXT NOT NULL, cover_image VARCHAR(500) NULL,
 status ENUM('draft','published') NOT NULL DEFAULT 'draft',
 author_id INT NULL, published_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (author_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
 id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, user_id INT NOT NULL,
 body TEXT NOT NULL, status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
 FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS blog_likes (
 id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, user_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_like(post_id,user_id),
 FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
 FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$extraColumns = [
 'stock_reintegrado' => "TINYINT(1) NOT NULL DEFAULT 0",
 'apellidos' => "VARCHAR(120) NULL",
 'rut' => "VARCHAR(15) NULL",
 'region' => "VARCHAR(120) NULL",
 'comuna' => "VARCHAR(120) NULL",
 'complemento' => "VARCHAR(255) NULL",
 'metodo_entrega' => "VARCHAR(30) NULL",
 'punto_retiro' => "VARCHAR(255) NULL",
 'direccion_facturacion' => "TEXT NULL",
 'descuento' => "INT UNSIGNED NOT NULL DEFAULT 0",
 'codigo_descuento' => "VARCHAR(50) NULL",
 'acepta_terminos' => "TINYINT(1) NOT NULL DEFAULT 0",
 'acepta_privacidad' => "TINYINT(1) NOT NULL DEFAULT 0"
];
foreach ($extraColumns as $column => $definition) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME=?");
    $check->execute([$column]);
    if (!(int)$check->fetchColumn()) $pdo->exec("ALTER TABLE pedidos ADD COLUMN `$column` $definition");
}


$paymentColumns = [
 'preference_id' => "VARCHAR(190) NULL",
 'mp_order_id' => "VARCHAR(190) NULL",
 'payment_id' => "VARCHAR(190) NULL",
 'external_reference' => "VARCHAR(190) NULL",
 'raw_response' => "LONGTEXT NULL",
 'updated_at' => "DATETIME NULL"
];
foreach ($paymentColumns as $column => $definition) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos' AND COLUMN_NAME=?");
    $check->execute([$column]);
    if (!(int)$check->fetchColumn()) $pdo->exec("ALTER TABLE pagos ADD COLUMN `$column` $definition");
}
$blogCategory = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blog_posts' AND COLUMN_NAME='category'")->fetchColumn();
if (!(int)$blogCategory) $pdo->exec("ALTER TABLE blog_posts ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT 'Novedades' AFTER title");

$exists = $pdo->query("SELECT id FROM usuarios WHERE username='admin'")->fetchColumn();
if (!$exists) {
    $stmt = $pdo->prepare("INSERT INTO usuarios(username,password,rol) VALUES(?,?,'admin')");
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
}
$count = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
if (!$count) {
    $seed = [
        ['Jardín interior (original)', 'Obra original firmada', 'Obra original', 120000, 1, 'images/demo/obra-01.svg'],
        ['Print Entre mundos A4', 'Impresión artística tamaño A4', 'Print', 12000, 15, 'images/demo/obra-02.svg'],
        ['Pack de stickers Fragmentos', 'Pack de stickers ilustrados', 'Stickers', 4500, 30, 'images/demo/obra-03.svg'],
        ['Pin Sueño turquesa', 'Pin esmaltado', 'Pines', 6000, 20, 'images/demo/obra-04.svg']
    ];
    $s = $pdo->prepare("INSERT INTO productos(nombre,descripcion,categoria,precio,stock,imagen) VALUES(?,?,?,?,?,?)");
    foreach ($seed as $p) $s->execute($p);
}
?>
<!doctype html>
<html lang="es">
<meta charset="utf-8">
<title>Instalación SHT</title>

<body style="font:16px Arial;max-width:650px;margin:50px auto;line-height:1.6">
    <h1>Instalación completada</h1>
    <p>Se crearon o actualizaron las tablas de tienda, pedidos, pagos y blog. También se agregaron los campos para Mercado Pago si aún no existían y productos de ejemplo si el catálogo estaba vacío.</p>
    <p><b>Usuario:</b> admin<br><b>Contraseña inicial:</b> admin123</p>
    <p>Inicia sesión y cambia la contraseña antes de publicar el sitio.</p>
    <p><a href="login.php">Ir al inicio de sesión</a></p>
    <p><b>Importante:</b> configura MERCADOPAGO_ACCESS_TOKEN, MERCADOPAGO_WEBHOOK_SECRET y APP_URL en el servidor. Después elimina o renombra install.php.</p>
</body>

</html>
