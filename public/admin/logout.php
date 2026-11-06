<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/session.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/', true, 303);
    exit;
}

if (!verify_csrf_token()) {
    http_response_code(403);
    exit('Your form session expired. Refresh the page and try again.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: /admin/login.php', true, 303);
exit;
