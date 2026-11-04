<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('customer');
$st = pdo()->prepare("SELECT b.*, v.name AS vname, v.reg_number, v.rent_per_day, v.pickup_location, v.branch, v.branch_contact
  FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.id=? AND b.user_id=? AND b.status='confirmed'");
$st->execute([(int)($_GET['id'] ?? 0), $me['id']]);
$b = $st->fetch();
$pageTitle = 'Invoice'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
if (!$b): ?>
  <div class="card" style="text-align:center"><h1>Invoice not available</h1><p class="muted">Invoices exist only for your confirmed bookings.</p><a class="btn" href="history.php">Back</a></div>
<?php include __DIR__ . '/../includes/footer.php'; exit; endif;
$days = (int)(new DateTime($b['pickup_dt']))->diff(new DateTime($b['return_dt']))->days;
?>
<div class="no-print" style="max-width:760px;margin:0 auto 1rem;display:flex;justify-content:space-between">
  <a href="history.php">← Back</a><button class="btn" data-print>🖨️ Print invoice</button></div>
<div class="invoice">
  <div class="invoice-head">
    <div><h1 style="margin:0">Rent<span style="color:var(--teal)">Ease</span></h1><div class="muted small">Rental Vehicle Management System</div></div>
    <div style="text-align:right"><h2 style="margin:0">INVOICE</h2><div class="small">No: <b>INV-<?= e($b['booking_code']) ?></b></div><div class="small">Date: <?= e(date('d M Y', strtotime($b['created_at']))) ?></div></div>
  </div>
  <div class="grid cols-2" style="margin-bottom:1rem">
    <div><b>Billed to</b><br><?= e($me['name']) ?><br><?= e($me['email']) ?><br>Licence: <?= e($me['licence_number']) ?> (<?= e($me['licence_type']) ?>)<br><?= e($me['phone']) ?></div>
    <div><b>Pickup / branch</b><br><?= e($b['pickup_location']) ?><br><?= e($b['branch']) ?><br><?= e($b['branch_contact']) ?></div>
  </div>
  <table>
    <thead><tr><th>Description</th><th>Period</th><th>Days</th><th>Rate/day</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
      <tr><td><?= e($b['vname']) ?><div class="small muted"><?= e($b['reg_number']) ?></div></td>
        <td><?= e(date('d M Y h:i A', strtotime($b['pickup_dt']))) ?><br>→ <?= e(date('d M Y h:i A', strtotime($b['return_dt']))) ?></td>
        <td><?= $days ?></td><td><?= e(money($b['rent_per_day'])) ?></td><td style="text-align:right"><?= e(money($b['total_rent'])) ?></td></tr>
      <tr class="total-row"><td colspan="4" style="text-align:right">Total</td><td style="text-align:right"><?= e(money($b['total_rent'])) ?></td></tr>
    </tbody>
  </table>
  <p class="small muted" style="margin-top:1.2rem">Booking ID: <?= e($b['booking_code']) ?> · Return status: <?= e($b['return_status']) ?>. Thank you for choosing RentEase.</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
