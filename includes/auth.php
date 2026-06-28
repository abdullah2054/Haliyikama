<?php
/**
 * Kimlik doğrulama ve yetki kontrol yardımcıları
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, name, email, phone, role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function requireLogin(string $redirect = '/login.php'): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . APP_URL . $redirect);
        exit;
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        include APP_ROOT . '/includes/header.php';
        echo '<div class="container mt-5 text-center">
                <h3>Erişim Reddedildi</h3>
                <p>Bu sayfayı görüntüleme yetkiniz yok.</p>
                <a href="' . APP_URL . '" class="btn-primary-custom">Ana Sayfa</a>
              </div>';
        include APP_ROOT . '/includes/footer.php';
        exit;
    }
}

function requireAdmin(): void
{
    requireRole('admin');
}

function requireCompany(): void
{
    requireRole('company');
}

function requireCustomer(): void
{
    requireRole('customer');
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Kullanıcının belirli bir siparişe erişim hakkı var mı?
 */
function canAccessOrder(array $order): bool
{
    $user = currentUser();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'customer') return (int)$order['customer_id'] === (int)$user['id'];
    if ($user['role'] === 'company') {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $company = $stmt->fetch();
        return $company && (int)$order['company_id'] === (int)$company['id'];
    }
    return false;
}
