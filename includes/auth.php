<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php'); exit;
    }
}
function require_admin(): void {
    require_login();
    if (($_SESSION['rol'] ?? '') !== 'admin') { http_response_code(403); exit('Acceso denegado.'); }
}
function clp($amount): string { return '$' . number_format((float)$amount, 0, ',', '.') . ' CLP'; }
