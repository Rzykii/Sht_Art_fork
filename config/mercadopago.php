<?php
/**
 * Credenciales de Mercado Pago.
 * Define estas variables en el servidor o completa los valores aquí en un entorno privado.
 * Nunca subas un Access Token real a un repositorio público.
 */
$localConfig = __DIR__ . '/mercadopago.local.php';
if (is_file($localConfig)) require $localConfig;
if (!defined('MP_ACCESS_TOKEN')) {
    define('MP_ACCESS_TOKEN', getenv('MERCADOPAGO_ACCESS_TOKEN') ?: ($MP_LOCAL_ACCESS_TOKEN ?? ''));
}
if (!defined('MP_WEBHOOK_SECRET')) {
    define('MP_WEBHOOK_SECRET', getenv('MERCADOPAGO_WEBHOOK_SECRET') ?: ($MP_LOCAL_WEBHOOK_SECRET ?? ''));
}
if (!defined('MP_APP_URL')) {
    define('MP_APP_URL', rtrim(getenv('APP_URL') ?: ($MP_LOCAL_APP_URL ?? ''), '/'));
}
