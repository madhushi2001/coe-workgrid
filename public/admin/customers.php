<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/session.php';
require_once dirname(__DIR__, 2) . '/app/database.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/admin_auth.php';

require_admin();

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$error = null;
$formName = '';
$formEmail = '';
$pdo = database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'toggle') {
            $accountId = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT);
            if ($accountId === false || $accountId < 1) {
                http_response_code(422);
                $error = 'Choose a valid customer account.';
            } else {
                try {
                    $accountQuery = $pdo->prepare('SELECT is_active FROM customer_accounts WHERE id = :id');
                    $accountQuery->execute(['id' => $accountId]);
                    $active = $accountQuery->fetchColumn();
                    if ($active === false) {
                        http_response_code(404);
                        $error = 'That customer account could not be found.';
                    } else {
                        $toggle = $pdo->prepare('UPDATE customer_accounts SET is_active = :is_active WHERE id = :id');
                        $toggle->execute(['is_active' => (int) $active === 1 ? 0 : 1, 'id' => $accountId]);
                        $_SESSION['admin_notice'] = 'Customer account status updated.';
                        header('Location: /admin/customers.php', true, 303);
                        exit;
                    }
                } catch (Throwable $exception) {
                    error_log('Admin customer account status update failed: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = 'The account status could not be updated. Check the database and try again.';
                }
            }
        } else {
            $formName = trim((string) ($_POST['full_name'] ?? ''));
            $formEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');

            if ($formName === '' || mb_strlen($formName) > 120) {
                http_response_code(422);
                $error = 'Enter the customer’s name (up to 120 characters).';
            } elseif (!filter_var($formEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($formEmail) > 190) {
                http_response_code(422);
                $error = 'Enter a valid email address.';
            } elseif (strlen($password) < 12 || strlen($password) > 4096) {
                http_response_code(422);
                $error = 'Choose a password between 12 and 4,096 characters.';
            } elseif (!hash_equals($password, $confirmation)) {
                http_response_code(422);
                $error = 'The passwords do not match.';
            } else {
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO customer_accounts (full_name, email, password_hash)
                         VALUES (:full_name, :email, :password_hash)'
                    );
                    $statement->execute([
                        'full_name' => $formName,
                        'email' => $formEmail,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $_SESSION['admin_notice'] = 'Customer account created.';
                    header('Location: /admin/customers.php', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        http_response_code(422);
                        $error = 'An account already uses that email address.';
                    } else {
                        error_log('Admin customer account creation failed: ' . $exception->getMessage());
                        http_response_code(503);
                        $error = 'The account could not be created. Check the database and try again.';
                    }
                } catch (Throwable $exception) {
                    error_log('Admin customer account creation failed: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = 'The account could not be created. Check the database and try again.';
                }
            }
        }
    }
}

try {
    $accounts = $pdo->query(
        'SELECT id, full_name, email, is_active, created_at
         FROM customer_accounts ORDER BY created_at DESC LIMIT 200'
    )->fetchAll();
    $adminQuery = $pdo->prepare('SELECT email FROM admin_users WHERE id = :id LIMIT 1');
    $adminQuery->execute(['id' => $_SESSION['admin_id']]);
    $adminEmail = $adminQuery->fetchColumn();
    if (!$adminEmail) {
        unset($_SESSION['admin_id']);
        header('Location: /admin/login.php', true, 303);
        exit;
    }
} catch (Throwable $exception) {
    error_log('Admin customer accounts could not load: ' . $exception->getMessage());
    http_response_code(503);
    $error = $error ?? 'Customer accounts could not load. Apply the customer accounts database migration and try again.';
    $accounts = [];
    $adminEmail = '';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Customers — COE Workgrid Admin</title>
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
            <a class="sidebar-link active" href="/admin/customers.php"><span>♙</span> Customers</a>
            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?><a class="sidebar-link" href="/admin/users.php"><span>⚙</span> Admin users</a><?php endif; ?>
            <a class="sidebar-link public-link" href="/#booking"><span>⌂</span> Public website <span class="external">↗</span></a>
        </nav>
        <div class="sidebar-bottom"><span class="online-dot"></span><span>Admin session</span></div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><span class="breadcrumb">ADMINISTRATION</span><span class="breadcrumb-separator">/</span><span>Customers</span></div>
            <div class="account-menu"><span class="account-avatar"><?= e(strtoupper(substr((string) $adminEmail, 0, 1))) ?></span><span><?= e($adminEmail) ?></span>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form>
            </div>
        </header>
        <section class="dashboard-content">
            <div class="page-heading"><div><p class="eyebrow">CUSTOMER ACCOUNTS</p><h1>Customers</h1><p class="page-subtitle">Create and manage customer account credentials.</p></div></div>
            <?php if ($notice): ?><div class="notice notice-success" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form class="package-card customer-create-form" method="post" action="/admin/customers.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="package-card-heading"><div><h2>Create customer account</h2><p>Passwords are stored as secure hashes.</p></div></div>
                <div class="package-fields">
                    <label>Full name<input name="full_name" maxlength="120" autocomplete="name" required value="<?= e($formName) ?>"></label>
                    <label>Email address<input name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e($formEmail) ?>"></label>
                    <label>Initial password<input name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><span class="field-hint">At least 12 characters. Share it privately with the customer.</span></label>
                    <label>Confirm password<input name="password_confirmation" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required></label>
                </div>
                <div class="package-actions"><button class="save-button" type="submit">Create account</button></div>
            </form>

            <section class="table-panel customer-list-panel" aria-label="Customer accounts">
                <div class="table-heading"><div><h2>Customer accounts</h2><p>Up to 200 most recently created accounts.</p></div></div>
                <div class="table-scroll">
                    <table><thead><tr><th>NAME</th><th>EMAIL</th><th>CREATED</th><th>STATUS</th><th>ACCOUNT</th></tr></thead>
                    <tbody>
                    <?php if (!$accounts): ?><tr><td class="empty-state" colspan="5">No customer accounts yet. Create the first account above.</td></tr><?php endif; ?>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><strong class="customer-name"><?= e($account['full_name']) ?></strong></td>
                            <td><a class="customer-contact" href="mailto:<?= e($account['email']) ?>"><?= e($account['email']) ?></a></td>
                            <td><?= e((new DateTimeImmutable($account['created_at']))->format('M j, Y')) ?></td>
                            <td><span class="status-badge <?= (int) $account['is_active'] === 1 ? 'status-confirmed' : 'status-cancelled' ?>"><?= (int) $account['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td><form method="post" action="/admin/customers.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="account_id" value="<?= e($account['id']) ?>"><button class="save-button account-toggle" type="submit"><?= (int) $account['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></td>
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
