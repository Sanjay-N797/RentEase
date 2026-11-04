<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('customer');
refresh_overdue();
$db = pdo();
$st = $db->prepare("SELECT
   SUM(status='confirmed' AND return_status<>'returned' AND return_dt >= NOW()) AS active_n,
   SUM(status='confirmed' AND return_status='returned') AS done_n,
   SUM(status='waitlisted') AS wait_n,
   COALESCE(SUM(CASE WHEN status='confirmed' THEN total_rent END),0) AS spent
   FROM bookings WHERE user_id=?");
$st->execute([$me['id']]);
$s = $st->fetch();
$pageTitle = 'Dashboard'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Hello, <?= e(explode(' ', $me['name'])[0]) ?> 👋</h1><p class="muted">Find your next ride and manage your bookings.</p></div>
  <a class="btn" href="explore.php">Explore all vehicles</a></div>
<div class="grid stats">
  <div class="stat"><div class="n"><?= (int)$s['active_n'] ?></div><div class="l">Active / upcoming bookings</div></div>
  <div class="stat"><div class="n"><?= (int)$s['wait_n'] ?></div><div class="l">On waitlist</div></div>
  <div class="stat"><div class="n"><?= (int)$s['done_n'] ?></div><div class="l">Completed rentals</div></div>
  <div class="stat"><div class="n"><?= e(money($s['spent'])) ?></div><div class="l">Total spent</div></div>
</div>
<div class="page-head"><h2>Available vehicles</h2><span class="live-status" id="feedStatus">Loading…</span></div>
<div class="vgrid" id="vehicleGrid" data-limit="6"><div class="empty">Loading vehicles…</div></div>
<p style="text-align:center;margin-top:1.2rem"><a class="btn btn-ghost" href="explore.php">See all vehicles →</a></p>
<?php include __DIR__ . '/../includes/footer.php'; ?>
