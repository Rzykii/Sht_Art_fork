<?php
require 'config/database.php';
require 'includes/auth.php';
require_once 'includes/mercadopago.php';

$orderId = (int)($_GET['order'] ?? 0);
if ($orderId < 1) { http_response_code(404); exit('Pedido no encontrado.'); }

// En InfinityFree gratuito los webhooks entrantes pueden ser bloqueados.
// Se consulta la API oficial al regresar el comprador, sin confiar en el parámetro status de la URL.
$syncError = false;
try {
    mp_sync_order_status($pdo, $orderId);
} catch (Throwable $e) {
    $syncError = true;
    error_log('Mercado Pago return sync: ' . $e->getMessage());
}

$q = $pdo->prepare('SELECT id,nombre_cliente,email,total,estado,created_at FROM pedidos WHERE id=?');
$q->execute([$orderId]);
$order = $q->fetch();
if (!$order) { http_response_code(404); exit('Pedido no encontrado.'); }

$p = $pdo->prepare("SELECT estado FROM pagos WHERE pedido_id=? AND proveedor='Mercado Pago' ORDER BY id DESC LIMIT 1");
$p->execute([$orderId]);
$paymentStatus = (string)($p->fetchColumn() ?: 'pendiente');
$label = $order['estado'] === 'pagado' ? 'Pago confirmado' : ($order['estado'] === 'cancelado' ? 'Pago no completado' : 'Pago en proceso');
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estado del pedido — SHT</title>
<?php if ($order['estado'] === 'pendiente'): ?><meta http-equiv="refresh" content="15"><?php endif; ?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/checkout.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">S</span><span>SHT.</span></a>
    <nav class="main-nav"><a href="tienda.php">Tienda</a><a href="blog.php">Blog</a></nav>
</header>
<main class="checkout-layout">
<section class="checkout-main checkout-success">
    <p class="eyebrow">PEDIDO #<?= (int)$order['id'] ?></p>
    <h1><?= h($label) ?></h1>
    <?php if ($order['estado'] === 'pagado'): ?>
        <p>¡Gracias! Mercado Pago confirmó tu pago. Comenzaremos a preparar tu pedido.</p>
    <?php elseif ($order['estado'] === 'cancelado'): ?>
        <p>El pago no fue completado. Si deseas intentarlo nuevamente, vuelve a la tienda y realiza otro pedido.</p>
    <?php else: ?>
        <p>Mercado Pago aún no confirma el pago. Esta página volverá a consultar el estado automáticamente. No realices otro pedido mientras se procesa.</p>
        <?php if ($syncError): ?><p class="checkout-muted">No pudimos consultar Mercado Pago en este momento. Actualiza esta página en unos segundos.</p><?php endif; ?>
    <?php endif; ?>
    <p><strong>Total:</strong> <?= clp($order['total']) ?></p>
    <p class="checkout-muted">Estado del pedido: <?= h($order['estado']) ?> · Estado del pago: <?= h($paymentStatus) ?></p>
    <a class="button button-primary" href="tienda.php">Volver a la tienda</a>
</section>
</main>
</body>
</html>
