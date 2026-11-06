<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/session.php';
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$workspaces = [
    ['id' => 1, 'name' => 'Open coworking', 'description' => 'A bright, flexible desk for focused days and fresh ideas.', 'tag' => 'Most flexible', 'icon' => '01', 'image' => '/assets/images/work3.jpg', 'image_alt' => 'Members working together in an open coworking studio', 'caption' => 'OPEN, SHARED WORK'],
    ['id' => 2, 'name' => 'Dedicated desk', 'description' => 'Make a comfortable, personal workspace your own.', 'tag' => 'Your own place', 'icon' => '02', 'image' => '/assets/images/work1.jpg', 'image_alt' => 'A dedicated desk in a bright coworking space', 'caption' => 'YOUR OWN DESK'],
    ['id' => 3, 'name' => 'Private office', 'description' => 'A quieter, more private home base for your team.', 'tag' => 'Room to grow', 'icon' => '03', 'image' => '/assets/images/work2.jpg', 'image_alt' => 'A private office desk beside large windows', 'caption' => 'A QUIETER PLACE TO FOCUS'],
];

$plans = [];
try {
    $planRows = database()->query(
        'SELECT id, name, monthly_price, description, features, is_featured
         FROM membership_plans WHERE is_active = 1 ORDER BY id'
    )->fetchAll();
    foreach ($planRows as $planRow) {
        $plans[] = [
            'id' => (int) $planRow['id'],
            'name' => $planRow['name'],
            'price' => number_format((float) $planRow['monthly_price'], 0, '.', ','),
            'description' => $planRow['description'],
            'features' => preg_split('/\r\n|\r|\n/', trim($planRow['features']), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            'featured' => (bool) $planRow['is_featured'],
        ];
    }
} catch (Throwable $exception) {
    error_log('Membership plans could not be loaded: ' . $exception->getMessage());
}

$errors = [];
$old = [];
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'workspace_id' => (string) ($_POST['workspace_id'] ?? ''),
        'plan_id' => (string) ($_POST['plan_id'] ?? ''),
        'start_date' => (string) ($_POST['start_date'] ?? ''),
        'end_date' => (string) ($_POST['end_date'] ?? ''),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];

    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $errors[] = 'Your form session expired. Refresh the page and try again.';
    } else {
        if ($old['name'] === '' || mb_strlen($old['name']) > 120) {
            $errors[] = 'Please enter your name (up to 120 characters).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($old['phone'] === '' || mb_strlen($old['phone']) > 40) {
            $errors[] = 'Please enter a phone number (up to 40 characters).';
        }
        $workspaceId = filter_var($old['workspace_id'], FILTER_VALIDATE_INT);
        if ($workspaceId === false || !in_array($workspaceId, array_column($workspaces, 'id'), true)) {
            $errors[] = 'Please choose an available workspace.';
        }
        $planId = filter_var($old['plan_id'], FILTER_VALIDATE_INT);
        if ($planId === false || !in_array($planId, array_column($plans, 'id'), true)) {
            $errors[] = 'Please choose a membership plan.';
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $old['start_date']);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $old['end_date']);
        $today = new DateTimeImmutable('today');
        if (!$start || $start->format('Y-m-d') !== $old['start_date'] || $start < $today) {
            $errors[] = 'Choose a start date today or later.';
        }
        if (!$end || $end->format('Y-m-d') !== $old['end_date'] || ($start && $end < $start)) {
            $errors[] = 'Choose an end date on or after your start date.';
        }
        if (mb_strlen($old['message']) > 1000) {
            $errors[] = 'Your message must be 1,000 characters or fewer.';
        }

        if ($errors === []) {
            try {
                $pdo = database();
                $statement = $pdo->prepare(
                    'INSERT INTO bookings (customer_name, email, phone, workspace_id, membership_plan_id, start_date, end_date, message)
                     VALUES (:customer_name, :email, :phone, :workspace_id, :membership_plan_id, :start_date, :end_date, :message)'
                );
                $statement->execute([
                    'customer_name' => $old['name'],
                    'email' => $old['email'],
                    'phone' => $old['phone'],
                    'workspace_id' => $workspaceId,
                    'membership_plan_id' => $planId,
                    'start_date' => $old['start_date'],
                    'end_date' => $old['end_date'],
                    'message' => $old['message'] !== '' ? $old['message'] : null,
                ]);
                $_SESSION['notice'] = 'Your request is in. Our team will be in touch soon.';
                header('Location: /#booking', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('Booking request could not be saved: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'We could not save your request right now. Please try again in a little while.';
            }
        } else {
            http_response_code(422);
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function selected(string $value, string|int $option): string
{
    return (string) $option === $value ? ' selected' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <meta name="description" content="A better place to do your best work. Find your focus, your people and your next idea at COE Workgrid.">
    <title>COE Workgrid — Make room for good work</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="#" aria-label="COE Workgrid home"><img class="brand-logo" src="/images/logo.jpg" alt=""><span>COE<span class="brand-light"> Workgrid</span></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Open navigation"><span></span><span></span></button>
    <nav class="main-nav" id="main-nav">
        <a href="#about">About</a><a href="#spaces">Spaces</a><a href="#membership">Membership</a><a href="#approach">Our approach</a><a href="#booking">Contact</a>
        <a class="nav-cta" href="#booking">Find your space <span aria-hidden="true">↗</span></a>
    </nav>
</header>

<main>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-dot"></span> A workspace for what’s next</p>
            <h1>Make room<br>for <span>good work.</span></h1>
            <p class="hero-description">A thoughtful place to focus, meet your people and turn the next big idea into the thing you do next.</p>
            <div class="hero-actions"><a class="button button-dark" href="#spaces">Explore the space <span>↗</span></a><a class="text-link" href="#membership">See memberships <span>↓</span></a></div>
            <div class="hero-proof"><div class="avatar-stack"><i>J</i><i>A</i><i>M</i><i>+</i></div><p><strong>A little more room to think.</strong><br>Join a community made for makers.</p></div>
        </div>
        <div class="hero-art" aria-label="Video showing a coworking workspace">
            <video class="hero-video" muted loop playsinline preload="metadata" poster="/assets/images/work3.jpg" aria-hidden="true">
                <source src="/assets/videos/videohero.mp4" type="video/mp4">
            </video>
            <div class="art-label"><span class="status-dot"></span> YOUR NEXT CHAPTER STARTS HERE</div>
            <div class="art-note"><span class="note-icon">✳</span><span><b>Your best ideas<br>need a good place.</b><small>There’s room for you here.</small></span></div>
            <div class="art-counter"><b>01</b><span>SPACE TO<br>THINK DIFFERENT</span></div>
        </div>
        <div class="hero-bottom"><span>FLEXIBLE DESKS&nbsp; · &nbsp;PRIVATE OFFICES&nbsp; · &nbsp;ROOM TO GROW</span><a href="#spaces">DISCOVER THE WORKSPACE&nbsp; ↓</a></div>
    </section>

    <section class="intro section-wrap" id="about">
        <div class="section-kicker">01 / THE WORKPLACE, REIMAGINED</div>
        <div class="intro-content"><h2>Work feels different<br><span>when it feels right.</span></h2><div><p>Not just a desk. Not quite the office. COE Workgrid brings together considered spaces and good people, so you can do work you’re proud of.</p><a class="underlined-link" href="#booking">Come see for yourself <span>↗</span></a></div></div>
        <div class="intro-stats"><div><strong>01</strong><span>Space to make<br>your own</span></div><div><strong>∞</strong><span>Ideas worth<br>sharing</span></div><div><strong>YOU</strong><span>Right where<br>you belong</span></div></div>
    </section>

    <section class="spaces-section" id="spaces">
        <div class="section-wrap">
            <div class="section-topline"><div class="section-kicker">02 / FIND YOUR KIND OF FOCUS</div><a class="underlined-link" href="#booking">Ask us what fits <span>↗</span></a></div>
            <div class="section-heading"><h2>A place for<br><span>your way of working.</span></h2><p>Come for a little while or settle in for the long haul. Find a setup that meets you where you are.</p></div>
            <div class="space-grid">
                <?php foreach ($workspaces as $index => $space): ?>
                    <article class="space-card space-card-<?= $index + 1 ?>">
                        <div class="space-visual"><img class="space-photo" src="<?= e($space['image']) ?>" alt="<?= e($space['image_alt']) ?>" loading="lazy"><button class="photo-trigger" type="button" aria-haspopup="dialog" aria-label="View full-size image: <?= e($space['name']) ?>" data-image-index="<?= e($index) ?>"></button><span class="space-number"><?= e($space['icon']) ?></span><span class="space-tag"><?= e($space['tag']) ?></span><span class="visual-caption"><?= e($space['caption']) ?></span></div>
                        <div class="space-info"><div><h3><?= e($space['name']) ?></h3><p><?= e($space['description']) ?></p></div><a href="#booking" aria-label="Ask about <?= e($space['name']) ?>">↗</a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="membership-section section-wrap" id="membership">
        <div class="section-kicker">03 / A MEMBERSHIP THAT MAKES SENSE</div>
        <div class="section-heading membership-heading"><h2>Good work has<br><span>no one-size-fits-all.</span></h2><p>Simple monthly plans designed around how you like to get things done.</p></div>
        <div class="plan-grid">
            <?php foreach ($plans as $plan): ?>
                <article class="plan-card<?= !empty($plan['featured']) ? ' plan-featured' : '' ?>">
                    <?php if (!empty($plan['featured'])): ?><span class="plan-badge">A GOOD PLACE TO START</span><?php endif; ?>
                    <p class="plan-name"><?= e($plan['name']) ?></p><p class="plan-description"><?= e($plan['description']) ?></p>
                    <p class="plan-price"><span>Rs</span><?= e($plan['price']) ?><small> / month</small></p>
                    <a class="plan-link" href="#booking">Make it yours <span>↗</span></a>
                    <div class="plan-divider"></div><p class="includes-label">THE GOOD STUFF</p>
                    <ul><?php foreach ($plan['features'] as $feature): ?><li><span>✓</span><?= e($feature) ?></li><?php endforeach; ?></ul>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="plan-note">Need something different? <a href="#booking">Let’s figure it out together.</a></p>
    </section>

    <section class="manifesto" id="approach">
        <div class="section-wrap journey-wrap">
            <div class="journey-heading"><div><p class="section-kicker">04 / A SIMPLE WAY TO BEGIN</p><h2>From first visit to<br><span>your everyday rhythm.</span></h2></div><p>Find your space, settle into a routine, and get on with the work that matters to you.</p></div>
            <div class="journey-steps">
                <article><span>01</span><h3>Discover</h3><p>Explore the spaces and find a setting that feels right.</p></article>
                <article><span>02</span><h3>Choose</h3><p>Pick a workspace and membership that fit your routine.</p></article>
                <article><span>03</span><h3>Get settled</h3><p>Send us a request and we’ll help with the next steps.</p></article>
                <article><span>04</span><h3>Do your thing</h3><p>Make room for focus, new ideas, and good work.</p></article>
            </div>
        </div>
    </section>

    <section class="booking-section" id="booking">
        <div class="section-wrap booking-layout">
            <div class="booking-copy"><div class="section-kicker">05 / YOUR NEXT STEP</div><h2>Let’s find your<br><span>place to work.</span></h2><p>Tell us a little about what you need. We’ll get back to you and help you find the right fit.</p><div class="booking-aside"><span>✳</span><p>No pressure. Just a good conversation<br>about doing your best work.</p></div></div>
            <form class="booking-form" method="post" action="/#booking">
                <h3>Tell us what you’re looking for.</h3><p class="form-intro">We’ll be in touch to talk through the details.</p>
                <?php if ($notice): ?><div class="form-alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
                <?php if ($errors): ?><div class="form-alert error" role="alert"><strong>We need to fix a couple of things:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="form-row"><label>Your name<input name="name" autocomplete="name" maxlength="120" placeholder="How should we call you?" value="<?= e($old['name'] ?? '') ?>" required></label><label>Email address<input name="email" type="email" autocomplete="email" maxlength="190" placeholder="you@example.com" value="<?= e($old['email'] ?? '') ?>" required></label></div>
                <div class="form-row"><label>Phone number<input name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+91 00000 00000" value="<?= e($old['phone'] ?? '') ?>" required></label><label>Workspace<select name="workspace_id" required><option value="">Choose a workspace</option><?php foreach ($workspaces as $space): ?><option value="<?= e($space['id']) ?>"<?= selected($old['workspace_id'] ?? '', $space['id']) ?>><?= e($space['name']) ?></option><?php endforeach; ?></select></label></div>
                <label>Membership plan<select name="plan_id" required><option value="">Choose a monthly plan</option><?php foreach ($plans as $plan): ?><option value="<?= e($plan['id']) ?>"<?= selected($old['plan_id'] ?? '', $plan['id']) ?>><?= e($plan['name']) ?> — Rs<?= e($plan['price']) ?>/month</option><?php endforeach; ?></select></label>
                <div class="form-row"><label>Preferred start date<input name="start_date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($old['start_date'] ?? '') ?>" required></label><label>Preferred end date<input name="end_date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($old['end_date'] ?? '') ?>" required></label></div>
                <label>A note for us <span class="optional">(optional)</span><textarea name="message" rows="3" maxlength="1000" placeholder="Anything you'd like us to know?"><?= e($old['message'] ?? '') ?></textarea></label>
                <button class="button button-dark submit-button" type="submit">Send a booking request <span>↗</span></button>
                <p class="privacy-note">By sending this request, you agree to be contacted about your workspace enquiry.</p>
            </form>
        </div>
    </section>
</main>

<dialog class="image-lightbox" aria-label="Workspace photo viewer">
    <button class="lightbox-close" type="button" aria-label="Close image viewer">×</button>
    <button class="lightbox-arrow lightbox-previous" type="button" aria-label="Previous image">←</button>
    <figure><img class="lightbox-image" src="" alt=""><figcaption class="lightbox-caption"></figcaption></figure>
    <button class="lightbox-arrow lightbox-next" type="button" aria-label="Next image">→</button>
</dialog>

<footer class="site-footer"><a class="brand footer-brand" href="#"><img class="brand-logo" src="/images/logo.jpg" alt=""><span>COE<span class="brand-light"> Workgrid</span></span></a><p>A little more room to think.</p><div><a href="#spaces">Spaces</a><a href="#membership">Membership</a><a href="#booking">Get in touch</a></div><small>© <?= date('Y') ?> COE Workgrid. Made for good work.</small></footer>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
