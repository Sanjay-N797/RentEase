<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('admin');
refresh_overdue();
$db = pdo();
$s = $db->query("SELECT
  (SELECT COUNT(*) FROM vehicles WHERE status<>'archived') AS vehicles,
  (SELECT COUNT(*) FROM vehicles WHERE status='published') AS published,
  (SELECT COUNT(*) FROM bookings WHERE status='confirmed' AND return_status<>'returned') AS active,
  (SELECT COUNT(*) FROM users WHERE role='customer') AS customers,
  (SELECT COALESCE(SUM(total_rent),0) FROM bookings WHERE status='confirmed') AS revenue")->fetch();
$recent = $db->query("SELECT b.booking_code, b.status, b.return_status, b.total_rent, b.pickup_dt, v.name vname, u.name uname
  FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id JOIN users u ON u.id=b.user_id ORDER BY b.created_at DESC, b.id DESC LIMIT 8")->fetchAll();
$overdue = $db->query("SELECT b.booking_code, b.return_dt, v.name vname, u.name uname, u.phone FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id JOIN users u ON u.id=b.user_id
  WHERE b.status='confirmed' AND b.return_status='overdue' ORDER BY b.return_dt")->fetchAll();
$pageTitle = 'Admin Dashboard'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Admin dashboard</h1><p class="muted">Welcome, <?= e($me['name']) ?>.</p></div><a class="btn" href="vehicle_form.php">+ Add vehicle</a></div>
<div class="grid stats">
  <div class="stat"><div class="n"><?= (int)$s['vehicles'] ?></div><div class="l">Total vehicles</div></div>
  <div class="stat"><div class="n"><?= (int)$s['published'] ?></div><div class="l">Published (live)</div></div>
  <div class="stat"><div class="n"><?= (int)$s['active'] ?></div><div class="l">Active bookings</div></div>
  <div class="stat"><div class="n"><?= (int)$s['customers'] ?></div><div class="l">Registered customers</div></div>
  <div class="stat"><div class="n"><?= e(money($s['revenue'])) ?></div><div class="l">Booked revenue</div></div>
</div>
<?php if ($overdue): ?>
<div class="card" style="margin-bottom:1.2rem;border-left:5px solid var(--danger)"><h2>⚠️ Overdue returns</h2>
  <?php foreach ($overdue as $o): ?><div class="small"><b><?= e($o['booking_code']) ?></b> · <?= e($o['vname']) ?> · <?= e($o['uname']) ?> (<?= e($o['phone']) ?>) · due <?= e(date('d M Y', strtotime($o['return_dt']))) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="page-head"><h2>Recent bookings</h2><a href="bookings.php">View all →</a></div>
<div class="table-wrap"><table>
  <thead><tr><th>Booking</th><th>Vehicle</th><th>Customer</th><th>Pickup</th><th>Total</th><th>Status</th><th>Return</th></tr></thead><tbody>
  <?php foreach ($recent as $b): ?><tr>
    <td><b><?= e($b['booking_code']) ?></b></td><td><?= e($b['vname']) ?></td><td><?= e($b['uname']) ?></td>
    <td><?= e(date('d M Y', strtotime($b['pickup_dt']))) ?></td><td><?= e(money($b['total_rent'])) ?></td>
    <td><span class="badge b-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td><td><span class="badge b-<?= e($b['return_status']) ?>"><?= e($b['return_status']) ?></span></td></tr>
  <?php endforeach; if (!$recent): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:1.5rem">No bookings yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
