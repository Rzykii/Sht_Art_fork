<?php
require 'config/database.php'; require 'includes/auth.php';
$orderId = (int)($_GET['order'] ?? 0);
$q = $pdo->prepare('SELECT id,nombre_cliente,email,total,estado,created_at FROM pedidos WHERE id=?');
$q->execute([$orderId]); $order = $q->fetch();
if (!$order) { http_response_code(404); exit('Pedido no encontrado.'); }
// El parámetro status del retorno solo sirve para orientar al usuario; el estado real viene del webhook.
$paymentStatus = 'pendiente';
$p = $pdo->prepare("SELECT estado FROM pagos WHERE pedido_id=? AND proveedor='Mercado Pago' ORDER BY id DESC LIMIT 1");
$p->execute([$orderId]); $paymentStatus = (string)($p->fetchColumn() ?: 'pendiente');
$label = $order['estado'] === 'pagado' ? 'Pago confirmado' : ($order['estado'] === 'cancelado' ? 'Pago no completado' : 'Pago en proceso');
?>
<!doctype html><html lang="es" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Estado del pedido — SHT</title><?php if($order['estado']==='pendiente'): ?><meta http-equiv="refresh" content="12"><?php endif; ?><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/checkout.css"></head><body><header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>SHT.</span></a><nav class="main-nav"><a href="tienda.php">Tienda</a><a href="blog.php">Blog</a></nav></header><main class="checkout-layout"><section class="checkout-main checkout-success"><p class="eyebrow">PEDIDO #<?= (int)$order['id'] ?></p><h1><?=h($label)?></h1><?php if($order['estado']==='pagado'):?><p>¡Gracias! Mercado Pago confirmó tu pago. Comenzaremos a preparar tu pedido.</p><?php elseif($order['estado']==='cancelado'):?><p>El pago no fue completado. Si deseas intentarlo nuevamente, vuelve a la tienda y realiza otro pedido.</p><?php else:?><p>Mercado Pago está procesando tu pago. El estado se actualizará automáticamente cuando recibamos la confirmación. No necesitas realizar el pedido otra vez.</p><?php endif;?><p><strong>Total:</strong> <?=clp($order['total'])?></p><p class="checkout-muted">Estado registrado: <?=h($order['estado'])?> · Estado del pago: <?=h($paymentStatus)?></p><a class="button button-primary" href="tienda.php">Volver a la tienda</a></section></main></body></html>
