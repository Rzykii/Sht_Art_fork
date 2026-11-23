<?php
require_once __DIR__ . '/../config/mercadopago.php';

function mp_request(string $method, string $endpoint, ?array $payload = null, array $extraHeaders = []): array {
    if (MP_ACCESS_TOKEN === '') {
        throw new RuntimeException('Mercado Pago no está configurado. Define MERCADOPAGO_ACCESS_TOKEN en el servidor.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL de PHP debe estar habilitada para usar Mercado Pago.');
    }
    $headers = array_merge([
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ], $extraHeaders);
    $ch = curl_init('https://api.mercadopago.com' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('No fue posible conectar con Mercado Pago: ' . $curlError);
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $message = is_array($data) ? ($data['message'] ?? $data['error'] ?? 'Respuesta no válida') : 'Respuesta no válida';
        throw new RuntimeException('Mercado Pago rechazó la operación (' . $status . '): ' . $message);
    }
    return $data;
}

function mp_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mp_create_order(string $email, int $orderId, int $total): array {
    if (MP_APP_URL === '') {
        throw new RuntimeException('Configura APP_URL con la URL pública de tu tienda para habilitar Mercado Pago.');
    }
    $base = MP_APP_URL;
    $payload = [
        'type' => 'online',
        'processing_mode' => 'manual',
        'total_amount' => (string)$total,
        'external_reference' => (string)$orderId,
        'description' => 'Pedido SHT #' . $orderId,
        'expiration_time' => 'P1D',
        'payer' => ['email' => $email],
        'items' => [[
            'title' => 'Pedido SHT #' . $orderId,
            'quantity' => 1,
            'unit_price' => (string)$total,
        ]],
        'config' => [
            'online' => [
                'success_url' => $base . '/checkout-result.php?order=' . $orderId . '&status=success',
                'pending_url' => $base . '/checkout-result.php?order=' . $orderId . '&status=pending',
                'failure_url' => $base . '/checkout-result.php?order=' . $orderId . '&status=failure',
                'auto_return' => 'approved',
            ],
        ],
    ];
    $order = mp_request('POST', '/v1/orders', $payload, ['X-Idempotency-Key: ' . mp_uuid()]);
    if (empty($order['id']) || empty($order['checkout_url'])) {
        throw new RuntimeException('Mercado Pago no devolvió una URL de checkout válida.');
    }
    return $order;
}

function mp_get_order(string $orderId): array {
    return mp_request('GET', '/v1/orders/' . rawurlencode($orderId));
}

/** Valida la firma HMAC del webhook siguiendo el manifiesto de Mercado Pago. */
function mp_validate_webhook_signature(string $dataId): bool {
    if (MP_WEBHOOK_SECRET === '') return false;
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    if ($signature === '' || $requestId === '' || $dataId === '') return false;
    $parts = [];
    foreach (explode(',', $signature) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) === 2) $parts[$pair[0]] = $pair[1];
    }
    if (empty($parts['ts']) || empty($parts['v1'])) return false;
    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $requestId . ';ts:' . $parts['ts'] . ';';
    $expected = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
    return hash_equals($expected, $parts['v1']);
}
