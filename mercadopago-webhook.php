<?php
require 'config/database.php';
require_once 'includes/mercadopago.php';

header('Content-Type: application/json; charset=utf-8');
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$type = $_GET['type'] ?? ($_GET['topic'] ?? ($payload['type'] ?? '')); 
$mpOrderId = (string)($_GET['data.id'] ?? ($_GET['data_id'] ?? ($payload['data']['id'] ?? '')));
if (!in_array($type, ['order', 'orders_v2'], true) || $mpOrderId === '') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}
if (!mp_validate_webhook_signature($mpOrderId)) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}
try {
    // Se consulta la order directamente: no se confía en el estado enviado por el navegador ni por el webhook.
    $mpOrder = mp_get_order($mpOrderId);
    if ((string)($mpOrder['id'] ?? '') !== $mpOrderId) throw new RuntimeException('El identificador de la order no coincide.');
    $orderId = (int)($mpOrder['external_reference'] ?? 0);
    if ($orderId < 1) throw new RuntimeException('Pedido SHT asociado no encontrado.');

    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT id,total,estado,stock_reintegrado FROM pedidos WHERE id=? FOR UPDATE');
    $q->execute([$orderId]);
    $order = $q->fetch();
    if (!$order) throw new RuntimeException('Pedido SHT no encontrado.');

    $mpTotal = (int)round((float)($mpOrder['total_amount'] ?? 0));
    $currency = (string)($mpOrder['currency'] ?? '');
    if ($mpTotal !== (int)$order['total'] || $currency !== 'CLP') {
        throw new RuntimeException('El monto o la moneda de la order no coincide con el pedido.');
    }

    $status = (string)($mpOrder['status'] ?? 'created');
    $orderStatus = 'pendiente';
    if ($status === 'processed') $orderStatus = 'pagado';
    elseif (in_array($status, ['failed', 'canceled', 'refunded', 'charged_back'], true)) $orderStatus = 'cancelado';

    $payments = $mpOrder['transactions']['payments'] ?? [];
    $transactionId = !empty($payments[0]['id']) ? (string)$payments[0]['id'] : null;
    $up = $pdo->prepare("UPDATE pagos SET payment_id=?,estado=?,updated_at=NOW() WHERE pedido_id=? AND proveedor='Mercado Pago' AND mp_order_id=?");
    $up->execute([$transactionId, $status, $orderId, $mpOrderId]);
    if ($up->rowCount() === 0) {
        $check = $pdo->prepare("SELECT id FROM pagos WHERE pedido_id=? AND proveedor='Mercado Pago' AND mp_order_id=?");
        $check->execute([$orderId, $mpOrderId]);
        if (!$check->fetchColumn()) throw new RuntimeException('La order notificada no corresponde a un pago registrado por SHT.');
    }
    $pdo->prepare('UPDATE pedidos SET estado=? WHERE id=?')->execute([$orderStatus, $orderId]);

    // Solo se devuelve stock si la compra aún no había sido pagada y la order falló o fue cancelada.
    if (in_array($status, ['failed', 'canceled'], true) && $order['estado'] !== 'pagado' && empty($order['stock_reintegrado'])) {
        $d = $pdo->prepare('SELECT producto_id,cantidad FROM detalle_pedido WHERE pedido_id=? AND producto_id IS NOT NULL');
        $d->execute([$orderId]);
        $restore = $pdo->prepare('UPDATE productos SET stock=stock+? WHERE id=?');
        foreach ($d->fetchAll() as $line) $restore->execute([(int)$line['cantidad'], (int)$line['producto_id']]);
        $pdo->prepare('UPDATE pedidos SET stock_reintegrado=1 WHERE id=?')->execute([$orderId]);
    }
    $pdo->commit();
    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mercado Pago Orders webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'processing_failed']);
}
