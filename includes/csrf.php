<?php
/**
 * CSRF koruma yardımcıları
 */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) ||
        (isset($_SESSION['csrf_expire']) && $_SESSION['csrf_expire'] < time())) {
        $_SESSION['csrf_token']  = bin2hex(random_bytes(32));
        $_SESSION['csrf_expire'] = time() + CSRF_TOKEN_EXPIRE;
    }
    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.');
    }
}
