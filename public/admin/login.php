<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/session.php';
require_once dirname(__DIR__, 2) . '/app/database.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/admin_auth.php';

if (authenticated_admin() !== null) {
    header('Location: /admin/', true, 303);
    exit;
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!verify_csrf_token()) {
        http_response_code(403);
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $statement = database()->prepare(
                'SELECT id, password_hash, role, is_active FROM admin_users WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $admin = $statement->fetch();
            $password = (string) ($_POST['password'] ?? '');

            if (strlen($password) <= 4096 && $admin && (int) $admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /admin/', true, 303);
                exit;
            }
            $error = 'The email or password is incorrect.';
        } catch (Throwable $exception) {
            error_log('Admin sign-in failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Admin sign-in is unavailable. Check the database setup and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Admin sign in — COE Workgrid</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
    <a class="admin-brand" href="/"><img class="admin-logo" src="/images/logo.jpg" alt=""><span>COE <span class="brand-light">Workgrid</span></span></a>
    <main class="login-card">
        <p class="eyebrow">WORKGRID ADMINISTRATION</p>
        <h1>Welcome back.</h1>
        <p class="login-intro">Sign in to manage your booking requests.</p>
        <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/admin/login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Email address<input name="email" type="email" autocomplete="username" maxlength="190" required autofocus value="<?= e($email) ?>"></label>
            <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
            <button type="submit" class="button button-primary">Sign in <span>↗</span></button>
        </form>
        <a class="back-link" href="/">← Back to COE Workgrid</a>
    </main>
</body>
</html>
