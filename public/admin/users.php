<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/session.php';
require_once dirname(__DIR__, 2) . '/app/database.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/admin_auth.php';

$currentAdmin = require_super_admin();
$pdo = database();
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$error = null;
$formName = '';
$formEmail = '';
$formRole = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'toggle') {
            $adminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
            if ($adminId === false || $adminId < 1) {
                http_response_code(422);
                $error = 'Choose a valid administrator account.';
            } elseif ($adminId === (int) $currentAdmin['id']) {
                http_response_code(422);
                $error = 'You cannot deactivate your own account.';
            } else {
                try {
                    $accountQuery = $pdo->prepare('SELECT role, is_active FROM admin_users WHERE id = :id');
                    $accountQuery->execute(['id' => $adminId]);
                    $account = $accountQuery->fetch();
                    if (!$account) {
                        http_response_code(404);
                        $error = 'That administrator account could not be found.';
                    } elseif ($account['role'] === 'super_admin' && (int) $account['is_active'] === 1) {
                        $activeSuperAdmins = (int) $pdo->query(
                            "SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1"
                        )->fetchColumn();
                        if ($activeSuperAdmins <= 1) {
                            http_response_code(422);
                            $error = 'Keep at least one active super admin account.';
                        }
                    }

                    if ($error === null) {
                        $toggle = $pdo->prepare('UPDATE admin_users SET is_active = :is_active WHERE id = :id');
                        $toggle->execute(['is_active' => (int) $account['is_active'] === 1 ? 0 : 1, 'id' => $adminId]);
                        $_SESSION['admin_notice'] = 'Administrator account status updated.';
                        header('Location: /admin/users.php', true, 303);
                        exit;
                    }
                } catch (Throwable $exception) {
                    error_log('Super admin account status update failed: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = 'The administrator status could not be updated. Check the database and try again.';
                }
            }
        } else {
            $formName = trim((string) ($_POST['full_name'] ?? ''));
            $formEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
            $formRole = (string) ($_POST['role'] ?? 'admin');
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');

            if ($formName === '' || mb_strlen($formName) > 120) {
                http_response_code(422);
                $error = 'Enter the administrator’s name (up to 120 characters).';
            } elseif (!filter_var($formEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($formEmail) > 190) {
                http_response_code(422);
                $error = 'Enter a valid email address.';
            } elseif (!in_array($formRole, ['admin', 'super_admin'], true)) {
                http_response_code(422);
                $error = 'Choose a valid administrator role.';
            } elseif (strlen($password) < 12 || strlen($password) > 4096) {
                http_response_code(422);
                $error = 'Choose a password between 12 and 4,096 characters.';
            } elseif (!hash_equals($password, $confirmation)) {
                http_response_code(422);
                $error = 'The passwords do not match.';
            } else {
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO admin_users (full_name, email, password_hash, role)
                         VALUES (:full_name, :email, :password_hash, :role)'
                    );
                    $statement->execute([
                        'full_name' => $formName,
                        'email' => $formEmail,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $formRole,
                    ]);
                    $_SESSION['admin_notice'] = 'Administrator account created.';
                    header('Location: /admin/users.php', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                        http_response_code(422);
                        $error = 'An administrator account already uses that email address.';
                    } else {
                        error_log('Super admin account creation failed: ' . $exception->getMessage());
                        http_response_code(503);
                        $error = 'The administrator account could not be created. Check the database and try again.';
                    }
                } catch (Throwable $exception) {
                    error_log('Super admin account creation failed: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = 'The administrator account could not be created. Check the database and try again.';
                }
            }
        }
    }
}

try {
    $adminAccounts = $pdo->query(
        'SELECT id, full_name, email, role, is_active, created_at
         FROM admin_users ORDER BY created_at DESC LIMIT 200'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('Administrator accounts could not load: ' . $exception->getMessage());
    http_response_code(503);
    $error = $error ?? 'Administrator accounts could not load. Check the database migration and try again.';
    $adminAccounts = [];
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Admin users — COE Workgrid</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="admin-brand" href="/"><img class="admin-logo" src="/images/logo.jpg" alt=""><span>COE <span class="brand-light">Workgrid</span></span></a>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="/admin/"><span>▤</span> Bookings</a>
            <a class="sidebar-link" href="/admin/plans.php"><span>◇</span> Packages</a>
            <a class="sidebar-link" href="/admin/customers.php"><span>♙</span> Customers</a>
            <a class="sidebar-link active" href="/admin/users.php"><span>⚙</span> Admin users</a>
            <a class="sidebar-link public-link" href="/#booking"><span>⌂</span> Public website <span class="external">↗</span></a>
        </nav>
        <div class="sidebar-bottom"><span class="online-dot"></span><span>Super admin session</span></div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><span class="breadcrumb">ADMINISTRATION</span><span class="breadcrumb-separator">/</span><span>Admin users</span></div>
            <div class="account-menu"><span class="account-avatar"><?= e(strtoupper(substr((string) $currentAdmin['email'], 0, 1))) ?></span><span><?= e($currentAdmin['email']) ?></span>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form>
            </div>
        </header>
        <section class="dashboard-content">
            <div class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Administrator accounts</h1><p class="page-subtitle">Create staff accounts and control what each role can manage.</p></div></div>
            <?php if ($notice): ?><div class="notice notice-success" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <div class="role-info-grid">
                <article class="role-info-card"><strong>Admin</strong><p>Manage bookings, membership packages, and customer accounts.</p></article>
                <article class="role-info-card"><strong>Super admin</strong><p>Everything an admin can do, plus create and deactivate administrator accounts.</p></article>
            </div>

            <form class="package-card admin-create-form" method="post" action="/admin/users.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="package-card-heading"><div><h2>Create administrator account</h2><p>Assign the least access needed for the staff member’s work.</p></div></div>
                <div class="package-fields">
                    <label>Full name<input name="full_name" maxlength="120" autocomplete="name" required value="<?= e($formName) ?>"></label>
                    <label>Email address<input name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e($formEmail) ?>"></label>
                    <label>Role<select name="role"><option value="admin"<?= $formRole === 'admin' ? ' selected' : '' ?>>Admin</option><option value="super_admin"<?= $formRole === 'super_admin' ? ' selected' : '' ?>>Super admin</option></select></label>
                    <label>Password<input name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><span class="field-hint">At least 12 characters. Share credentials privately.</span></label>
                    <label>Confirm password<input name="password_confirmation" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required></label>
                </div>
                <div class="package-actions"><button class="save-button" type="submit">Create admin account</button></div>
            </form>

            <section class="table-panel admin-users-list" aria-label="Administrator accounts">
                <div class="table-heading"><div><h2>Administrator accounts</h2><p>Up to 200 most recently created accounts.</p></div></div>
                <div class="table-scroll">
                    <table><thead><tr><th>NAME</th><th>EMAIL</th><th>ROLE</th><th>STATUS</th><th>CREATED</th><th>ACCOUNT</th></tr></thead>
                    <tbody>
                    <?php if (!$adminAccounts): ?><tr><td class="empty-state" colspan="6">No administrator accounts yet.</td></tr><?php endif; ?>
                    <?php foreach ($adminAccounts as $account): ?>
                        <tr>
                            <td><strong class="customer-name"><?= e($account['full_name']) ?></strong></td>
                            <td><a class="customer-contact" href="mailto:<?= e($account['email']) ?>"><?= e($account['email']) ?></a></td>
                            <td><span class="role-badge"><?= $account['role'] === 'super_admin' ? 'Super admin' : 'Admin' ?></span></td>
                            <td><span class="status-badge <?= (int) $account['is_active'] === 1 ? 'status-confirmed' : 'status-cancelled' ?>"><?= (int) $account['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= e((new DateTimeImmutable($account['created_at']))->format('M j, Y')) ?></td>
                            <td><?php if ((int) $account['id'] === (int) $currentAdmin['id']): ?><span class="current-account-label">You</span><?php else: ?><form method="post" action="/admin/users.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="admin_id" value="<?= e($account['id']) ?>"><button class="save-button account-toggle" type="submit"><?= (int) $account['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            </section>
        </section>
    </main>
</div>
</body>
</html>
