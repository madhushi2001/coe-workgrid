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
$formData = null;
$pdo = database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        $planId = isset($_POST['plan_id']) ? filter_var($_POST['plan_id'], FILTER_VALIDATE_INT) : null;

        if ($action === 'toggle' && $planId !== false && $planId > 0) {
            try {
                $statement = $pdo->prepare('SELECT is_active FROM membership_plans WHERE id = :id');
                $statement->execute(['id' => $planId]);
                $current = $statement->fetchColumn();
                if ($current === false) {
                    $error = 'That package could not be found.';
                } elseif ((int) $current === 1 && (int) $pdo->query('SELECT COUNT(*) FROM membership_plans WHERE is_active = 1')->fetchColumn() <= 1) {
                    $error = 'Keep at least one package active so customers can submit requests.';
                } else {
                    $toggle = $pdo->prepare('UPDATE membership_plans SET is_active = :active, is_featured = IF(:featured_active = 1, is_featured, 0) WHERE id = :id');
                    $nextActive = (int) $current === 1 ? 0 : 1;
                    $toggle->execute(['active' => $nextActive, 'featured_active' => $nextActive, 'id' => $planId]);
                    $_SESSION['admin_notice'] = 'Package availability updated.';
                    header('Location: /admin/plans.php', true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('Admin package availability update failed: ' . $exception->getMessage());
                http_response_code(503);
                $error = 'Package availability could not be updated. Check the database and try again.';
            }
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $price = trim((string) ($_POST['monthly_price'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $features = trim(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['features'] ?? '')));
            $active = isset($_POST['is_active']) ? 1 : 0;
            $featured = isset($_POST['is_featured']) && $active === 1 ? 1 : 0;
            $formData = [
                'id' => $planId === false ? null : $planId,
                'name' => $name,
                'monthly_price' => $price,
                'description' => $description,
                'features' => $features,
                'is_active' => $active,
                'is_featured' => $featured,
            ];

            $featureLines = array_filter(array_map('trim', explode("\n", $features)), static fn (string $line): bool => $line !== '');
            if ($planId === false) {
                $error = 'Choose a valid package.';
            } elseif ($name === '' || mb_strlen($name) > 100) {
                $error = 'Enter a package name up to 100 characters.';
            } elseif (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price) || (float) $price <= 0) {
                $error = 'Enter a monthly price greater than zero with up to 2 decimal places.';
            } elseif (mb_strlen($description) > 500) {
                $error = 'Keep the description to 500 characters or fewer.';
            } elseif (count($featureLines) > 10 || array_filter($featureLines, static fn (string $line): bool => mb_strlen($line) > 120)) {
                $error = 'Add up to 10 benefits, with each benefit 120 characters or fewer.';
            } elseif ($planId !== false && $planId !== null && $planId < 1) {
                $error = 'Choose a valid package.';
            } else {
                try {
                    if ($planId !== false && $planId !== null) {
                        $exists = $pdo->prepare('SELECT id, is_active FROM membership_plans WHERE id = :id');
                        $exists->execute(['id' => $planId]);
                        $existing = $exists->fetch();
                        if (!$existing) {
                            throw new RuntimeException('That package could not be found.');
                        }
                        if ((int) $existing['is_active'] === 1 && $active === 0 && (int) $pdo->query('SELECT COUNT(*) FROM membership_plans WHERE is_active = 1')->fetchColumn() <= 1) {
                            throw new RuntimeException('Keep at least one package active so customers can submit requests.');
                        }
                    }

                    $pdo->beginTransaction();
                    if ($featured === 1) {
                        $pdo->exec('UPDATE membership_plans SET is_featured = 0');
                    }
                    if ($planId !== false && $planId !== null) {
                        $statement = $pdo->prepare(
                            'UPDATE membership_plans
                             SET name = :name, monthly_price = :price, description = :description,
                                 features = :features, is_active = :active, is_featured = :featured
                             WHERE id = :id'
                        );
                        $statement->execute([
                            'name' => $name,
                            'price' => $price,
                            'description' => $description,
                            'features' => implode("\n", $featureLines),
                            'active' => $active,
                            'featured' => $featured,
                            'id' => $planId,
                        ]);
                    } else {
                        $statement = $pdo->prepare(
                            'INSERT INTO membership_plans (name, monthly_price, description, features, is_active, is_featured)
                             VALUES (:name, :price, :description, :features, :active, :featured)'
                        );
                        $statement->execute([
                            'name' => $name,
                            'price' => $price,
                            'description' => $description,
                            'features' => implode("\n", $featureLines),
                            'active' => $active,
                            'featured' => $featured,
                        ]);
                    }
                    $pdo->commit();
                    $_SESSION['admin_notice'] = 'Package saved. Active packages now appear on the public website.';
                    header('Location: /admin/plans.php', true, 303);
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Admin package save failed: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The package could not be saved. Check the database and try again.';
                }
            }
        }
    }
}

try {
    $plans = $pdo->query('SELECT id, name, monthly_price, description, features, is_active, is_featured FROM membership_plans ORDER BY id')->fetchAll();
    $adminQuery = $pdo->prepare('SELECT email FROM admin_users WHERE id = :id LIMIT 1');
    $adminQuery->execute(['id' => $_SESSION['admin_id']]);
    $adminEmail = $adminQuery->fetchColumn();
    if (!$adminEmail) {
        unset($_SESSION['admin_id']);
        header('Location: /admin/login.php', true, 303);
        exit;
    }
} catch (Throwable $exception) {
    error_log('Admin packages could not load: ' . $exception->getMessage());
    http_response_code(503);
    $error = $error ?? 'Packages could not load. Apply the membership package database migration and try again.';
    $plans = [];
    $adminEmail = '';
}

function package_form(array $plan = []): array
{
    return [
        'id' => $plan['id'] ?? null,
        'name' => $plan['name'] ?? '',
        'monthly_price' => $plan['monthly_price'] ?? '',
        'description' => $plan['description'] ?? '',
        'features' => $plan['features'] ?? '',
        'is_active' => (int) ($plan['is_active'] ?? 1),
        'is_featured' => (int) ($plan['is_featured'] ?? 0),
    ];
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Packages — COE Workgrid Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="admin-brand" href="/"><img class="admin-logo" src="/images/logo.jpg" alt=""><span>COE <span class="brand-light">Workgrid</span></span></a>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="/admin/"><span>▤</span> Bookings</a>
            <a class="sidebar-link active" href="/admin/plans.php"><span>◇</span> Packages</a>
            <a class="sidebar-link" href="/admin/customers.php"><span>♙</span> Customers</a>
            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?><a class="sidebar-link" href="/admin/users.php"><span>⚙</span> Admin users</a><?php endif; ?>
            <a class="sidebar-link public-link" href="/#membership"><span>⌂</span> Public website <span class="external">↗</span></a>
        </nav>
        <div class="sidebar-bottom"><span class="online-dot"></span><span>Admin session</span></div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><span class="breadcrumb">ADMINISTRATION</span><span class="breadcrumb-separator">/</span><span>Packages</span></div>
            <div class="account-menu"><span class="account-avatar"><?= e(strtoupper(substr((string) $adminEmail, 0, 1))) ?></span><span><?= e($adminEmail) ?></span>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form>
            </div>
        </header>
        <section class="dashboard-content">
            <div class="page-heading"><div><p class="eyebrow">PUBLIC MEMBERSHIPS</p><h1>Manage packages</h1><p class="page-subtitle">Edit prices and benefits. Active packages appear on the public site and booking form.</p></div><a class="button button-outline" href="/#membership">View public site <span>↗</span></a></div>
            <?php if ($notice): ?><div class="notice notice-success" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <section class="package-list" aria-label="Existing packages">
                <?php foreach ($plans as $plan): ?>
                    <?php $form = $formData !== null && (string) ($formData['id'] ?? '') === (string) $plan['id'] ? $formData : package_form($plan); ?>
                    <form class="package-card" method="post" action="/admin/plans.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="plan_id" value="<?= e($plan['id']) ?>">
                        <input type="hidden" name="action" value="save">
                        <div class="package-card-heading"><div><h2><?= e($plan['name']) ?></h2><p><?= (int) $plan['is_active'] === 1 ? 'Active on public site' : 'Archived from public site' ?></p></div><span class="package-price">₹<?= e(number_format((float) $plan['monthly_price'], 2)) ?> / month</span></div>
                        <div class="package-fields">
                            <label>Package name<input name="name" maxlength="100" required value="<?= e($form['name']) ?>"></label>
                            <label>Monthly price (₹)<input name="monthly_price" type="number" min="0.01" max="99999999.99" step="0.01" required value="<?= e($form['monthly_price']) ?>"></label>
                            <label class="package-wide">Description<textarea name="description" rows="2" maxlength="500"><?= e($form['description']) ?></textarea></label>
                            <label class="package-wide">Benefits <span class="field-hint">One benefit per line, up to 10.</span><textarea name="features" rows="4" maxlength="1500"><?= e($form['features']) ?></textarea></label>
                        </div>
                        <div class="package-actions">
                            <label class="check-label"><input type="checkbox" name="is_active" value="1"<?= (int) $form['is_active'] === 1 ? ' checked' : '' ?>> Active</label>
                            <label class="check-label"><input type="checkbox" name="is_featured" value="1"<?= (int) $form['is_featured'] === 1 ? ' checked' : '' ?>> Highlight on site</label>
                            <button class="save-button" type="submit">Save package</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </section>

            <?php $newForm = $formData !== null && empty($formData['id']) ? $formData : package_form(); ?>
            <form class="package-card package-new" method="post" action="/admin/plans.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <div class="package-card-heading"><div><h2>Add a package</h2><p>Create another membership option.</p></div></div>
                <div class="package-fields">
                    <label>Package name<input name="name" maxlength="100" required value="<?= e($newForm['name']) ?>"></label>
                    <label>Monthly price (₹)<input name="monthly_price" type="number" min="0.01" max="99999999.99" step="0.01" required value="<?= e($newForm['monthly_price']) ?>"></label>
                    <label class="package-wide">Description<textarea name="description" rows="2" maxlength="500"><?= e($newForm['description']) ?></textarea></label>
                    <label class="package-wide">Benefits <span class="field-hint">One benefit per line, up to 10.</span><textarea name="features" rows="4" maxlength="1500"><?= e($newForm['features']) ?></textarea></label>
                </div>
                <div class="package-actions">
                    <label class="check-label"><input type="checkbox" name="is_active" value="1"<?= (int) $newForm['is_active'] === 1 ? ' checked' : '' ?>> Active</label>
                    <label class="check-label"><input type="checkbox" name="is_featured" value="1"<?= (int) $newForm['is_featured'] === 1 ? ' checked' : '' ?>> Highlight on site</label>
                    <button class="save-button" type="submit">Add package</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
