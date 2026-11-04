<?php
/* Expects: $pageTitle (string), $area ('admin' | 'customer' | 'public'), optional $bodyClass */
require_once __DIR__ . '/auth.php';
$area = $area ?? 'public';
$me = current_user();
$script = basename($_SERVER['SCRIPT_NAME']);
$inAdminDir = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/admin/') !== false;
function nav_link(string $href, string $label, string $script): string {
    $active = basename($href) === $script ? ' class="active"' : '';
    return '<a href="' . e(url($href)) . '"' . $active . '>' . e($label) . '</a>';
}
$home = ($me && $me['role'] === 'admin') ? 'admin/dashboard.php' : (($me && $me['role'] === 'customer') ? 'customer/dashboard.php' : 'customer/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title><?= e($pageTitle ?? 'RentEase') ?> · RentEase</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?> area-<?= e($area) ?>">
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e(url($home)) ?>">
      <span class="brand-mark">R</span> Rent<b>Ease</b><?php if ($area === 'admin' || $inAdminDir): ?><small>Admin</small><?php endif; ?>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" id="mainNav">
      <?php if ($area === 'admin' && $me && $me['role'] === 'admin'): ?>
        <?= nav_link('admin/dashboard.php', 'Dashboard', $script) ?>
        <?= nav_link('admin/vehicles.php', 'Vehicles', $script) ?>
        <?= nav_link('admin/vehicle_form.php', 'Add Vehicle', $script) ?>
        <?= nav_link('admin/bookings.php', 'Bookings', $script) ?>
        <?= nav_link('admin/reports.php', 'Reports', $script) ?>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="inline-form"><?= csrf_field() ?><button class="nav-btn" type="submit">Logout</button></form>
      <?php elseif ($area === 'customer' && $me && $me['role'] === 'customer'): ?>
        <?= nav_link('customer/dashboard.php', 'Dashboard', $script) ?>
        <?= nav_link('customer/explore.php', 'Explore', $script) ?>
        <?= nav_link('customer/bookings.php', 'My Bookings', $script) ?>
        <?= nav_link('customer/history.php', 'History & Invoices', $script) ?>
        <?= nav_link('customer/profile.php', 'Profile', $script) ?>
        <form method="post" action="<?= e(url('customer/logout.php')) ?>" class="inline-form"><?= csrf_field() ?><button class="nav-btn" type="submit">Logout</button></form>
      <?php else: ?>
        <a href="<?= e(url('customer/login.php')) ?>" class="<?= !$inAdminDir && $script === 'login.php' ? 'active' : '' ?>">Customer Login</a>
        <a href="<?= e(url('customer/register.php')) ?>" class="<?= $script === 'register.php' ? 'active' : '' ?>">Register</a>
        <a href="<?= e(url('admin/login.php')) ?>" class="<?= $inAdminDir ? 'active' : '' ?>">Admin Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php foreach (flash_get() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?>" role="alert"><?= e($m) ?></div>
<?php endforeach; ?>
