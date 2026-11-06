<?php
declare(strict_types=1);

function authenticated_admin(): ?array
{
    if (!isset($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) {
        return null;
    }

    $statement = database()->prepare(
        'SELECT id, full_name, email, role, is_active FROM admin_users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $_SESSION['admin_id']]);
    $admin = $statement->fetch();
    if (!$admin || (int) $admin['is_active'] !== 1) {
        unset($_SESSION['admin_id'], $_SESSION['admin_role']);
        return null;
    }

    $_SESSION['admin_role'] = $admin['role'];
    return $admin;
}

function require_admin(): array
{
    $admin = authenticated_admin();
    if ($admin === null) {
        header('Location: /admin/login.php', true, 303);
        exit;
    }

    return $admin;
}

function require_super_admin(): array
{
    $admin = require_admin();
    if ($admin['role'] !== 'super_admin') {
        http_response_code(403);
        exit('Super admin access is required to manage administrator accounts.');
    }

    return $admin;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): bool
{
    return hash_equals(
        (string) ($_SESSION['csrf_token'] ?? ''),
        (string) ($_POST['csrf_token'] ?? '')
    );
}
