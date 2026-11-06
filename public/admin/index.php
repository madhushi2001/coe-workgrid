<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/session.php';
require_once dirname(__DIR__, 2) . '/app/database.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/admin_auth.php';

require_admin();

$statuses = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'awaiting_payment' => 'Awaiting payment',
    'confirmed' => 'Confirmed',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
];
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = (string) ($_POST['status'] ?? '');
        if ($bookingId === false || $bookingId < 1 || !isset($statuses[$newStatus])) {
            http_response_code(422);
            $error = 'Choose a valid booking and status.';
        } else {
            try {
                $statement = database()->prepare(
                    'UPDATE bookings SET status = :status WHERE id = :id'
                );
                $statement->execute(['status' => $newStatus, 'id' => $bookingId]);
                if ($statement->rowCount() === 0) {
                    $check = database()->prepare('SELECT id FROM bookings WHERE id = :id');
                    $check->execute(['id' => $bookingId]);
                    if (!$check->fetch()) {
                        http_response_code(404);
                        $error = 'That booking could not be found.';
                    } else {
                        $_SESSION['admin_notice'] = 'Booking status saved.';
                        header('Location: /admin/', true, 303);
                        exit;
                    }
                } else {
                    $_SESSION['admin_notice'] = 'Booking status saved.';
                    header('Location: /admin/', true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('Admin booking update failed: ' . $exception->getMessage());
                http_response_code(503);
                $error = 'The booking update could not be saved. Check the database and try again.';
            }
        }
    }
}

$filter = (string) ($_GET['status'] ?? '');
if ($filter !== '' && !isset($statuses[$filter])) {
    http_response_code(400);
    $filter = '';
    $error = 'That status filter is not valid.';
}

try {
    $pdo = database();
    $summary = $pdo->query(
        "SELECT COUNT(*) AS total,
            SUM(status = 'pending') AS pending,
            SUM(status = 'confirmed') AS confirmed,
            SUM(status = 'awaiting_payment') AS awaiting_payment
         FROM bookings"
    )->fetch();

    $sql = 'SELECT b.id, b.customer_name, b.email, b.phone, b.start_date, b.end_date,
                   b.message, b.status, b.created_at,
                   w.name AS workspace_name, mp.name AS plan_name
            FROM bookings b
            INNER JOIN workspaces w ON w.id = b.workspace_id
            INNER JOIN membership_plans mp ON mp.id = b.membership_plan_id';
    $parameters = [];
    if ($filter !== '') {
        $sql .= ' WHERE b.status = :status';
        $parameters['status'] = $filter;
    }
    $sql .= ' ORDER BY b.created_at DESC LIMIT 100';
    $bookingsQuery = $pdo->prepare($sql);
    $bookingsQuery->execute($parameters);
    $bookings = $bookingsQuery->fetchAll();

    $adminQuery = $pdo->prepare('SELECT email FROM admin_users WHERE id = :id LIMIT 1');
    $adminQuery->execute(['id' => $_SESSION['admin_id']]);
    $adminEmail = $adminQuery->fetchColumn();
    if (!$adminEmail) {
        unset($_SESSION['admin_id']);
        header('Location: /admin/login.php', true, 303);
        exit;
    }
} catch (Throwable $exception) {
    error_log('Admin dashboard could not load: ' . $exception->getMessage());
    http_response_code(503);
    $error = $error ?? 'The dashboard could not load. Check the database setup and try again.';
    $summary = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'awaiting_payment' => 0];
    $bookings = [];
    $adminEmail = '';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Bookings — COE Workgrid Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="admin-brand" href="/"><img class="admin-logo" src="/images/logo.jpg" alt=""><span>COE <span class="brand-light">Workgrid</span></span></a>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="sidebar-nav">
            <a class="sidebar-link active" href="/admin/"><span>▤</span> Bookings</a>
            <a class="sidebar-link" href="/admin/plans.php"><span>◇</span> Packages</a>
            <a class="sidebar-link" href="/admin/customers.php"><span>♙</span> Customers</a>
            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?><a class="sidebar-link" href="/admin/users.php"><span>⚙</span> Admin users</a><?php endif; ?>
            <a class="sidebar-link public-link" href="/#spaces"><span>⌂</span> Public website <span class="external">↗</span></a>
        </nav>
        <div class="sidebar-bottom"><span class="online-dot"></span><span>Admin session</span></div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><span class="breadcrumb">ADMINISTRATION</span><span class="breadcrumb-separator">/</span><span>Bookings</span></div>
            <div class="account-menu"><span class="account-avatar"><?= e(strtoupper(substr((string) $adminEmail, 0, 1))) ?></span><span><?= e($adminEmail) ?></span>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form>
            </div>
        </header>
        <section class="dashboard-content">
            <div class="page-heading"><div><p class="eyebrow">YOUR SPACE AT A GLANCE</p><h1>Booking requests</h1><p class="page-subtitle">A clear view of who's looking for a place to work.</p></div><a class="button button-outline" href="/#booking">View public site <span>↗</span></a></div>
            <?php if ($notice): ?><div class="notice notice-success" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="stats-grid">
                <article class="stat-card"><span class="stat-label">TOTAL REQUESTS</span><strong><?= e($summary['total'] ?? 0) ?></strong><span class="stat-caption">All time</span><span class="stat-icon">▤</span></article>
                <article class="stat-card"><span class="stat-label">NEEDS A LOOK</span><strong><?= e($summary['pending'] ?? 0) ?></strong><span class="stat-caption">Pending requests</span><span class="stat-icon stat-icon-blue">◷</span></article>
                <article class="stat-card"><span class="stat-label">CONFIRMED</span><strong><?= e($summary['confirmed'] ?? 0) ?></strong><span class="stat-caption">Ready to welcome</span><span class="stat-icon stat-icon-green">✓</span></article>
                <article class="stat-card"><span class="stat-label">AWAITING PAYMENT</span><strong><?= e($summary['awaiting_payment'] ?? 0) ?></strong><span class="stat-caption">Follow up with customer</span><span class="stat-icon stat-icon-amber">Rs</span></article>
            </div>
            <section class="table-panel">
                <div class="table-heading"><div><h2>Latest requests</h2><p>Review and update booking status.</p></div>
                    <form class="filter-form" method="get" action="/admin/"><label for="status-filter">Status</label><select id="status-filter" name="status" onchange="this.form.submit()"><option value="">All requests</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>"<?= $filter === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
                </div>
                <div class="table-scroll">
                    <table><thead><tr><th>PERSON</th><th>WORKSPACE & PLAN</th><th>DATES</th><th>REQUESTED</th><th>STATUS</th><th>UPDATE</th></tr></thead>
                    <tbody>
                    <?php if (!$bookings): ?><tr><td class="empty-state" colspan="6"><?= $filter ? 'No requests have this status yet.' : 'No booking requests yet. New requests will show up here.' ?></td></tr><?php endif; ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong class="customer-name"><?= e($booking['customer_name']) ?></strong><a class="customer-contact" href="mailto:<?= e($booking['email']) ?>"><?= e($booking['email']) ?></a><span class="customer-contact"><?= e($booking['phone']) ?></span><?php if ($booking['message']): ?><details class="customer-message"><summary>Read note</summary><p><?= e($booking['message']) ?></p></details><?php endif; ?></td>
                            <td><strong><?= e($booking['workspace_name']) ?></strong><span class="table-secondary"><?= e($booking['plan_name']) ?></span></td>
                            <td><strong><?= e((new DateTimeImmutable($booking['start_date']))->format('M j, Y')) ?></strong><span class="table-secondary">to <?= e((new DateTimeImmutable($booking['end_date']))->format('M j, Y')) ?></span></td>
                            <td><?= e((new DateTimeImmutable($booking['created_at']))->format('M j, Y')) ?></td>
                            <td><span class="status-badge status-<?= e($booking['status']) ?>"><?= e($statuses[$booking['status']] ?? $booking['status']) ?></span></td>
                            <td><form class="status-form" method="post" action="/admin/"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>"><label class="sr-only" for="status-<?= e($booking['id']) ?>">Update status for <?= e($booking['customer_name']) ?></label><select id="status-<?= e($booking['id']) ?>" name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>"<?= $booking['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button type="submit" aria-label="Save status for <?= e($booking['customer_name']) ?>">Save</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <p class="table-footnote">Showing up to 100 most recent requests<?= $filter ? ' · ' . e($statuses[$filter]) : '' ?>.</p>
            </section>
        </section>
    </main>
</div>
</body>
</html>
