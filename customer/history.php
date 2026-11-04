<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('customer');
refresh_overdue();
$st = pdo()->prepare("SELECT b.*, v.name AS vname FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
  WHERE b.user_id=? AND (b.status='cancelled' OR b.return_status='returned' OR b.return_dt < NOW())
  ORDER BY b.pickup_dt DESC");
$st->execute([$me['id']]);
$rows = $st->fetchAll();
$pageTitle = 'History & Invoices'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Rental history &amp; invoices</h1><p class="muted">Past, returned and cancelled bookings. Invoices are available for confirmed bookings.</p></div></div>
<div class="table-wrap"><table>
  <thead><tr><th>Booking ID</th><th>Vehicle</th><th>Period</th><th>Total</th><th>Status</th><th>Return</th><th>Invoice</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $b): ?>
    <tr>
      <td><b><?= e($b['booking_code']) ?></b></td><td><?= e($b['vname']) ?></td>
      <td><?= e(date('d M Y', strtotime($b['pickup_dt']))) ?> → <?= e(date('d M Y', strtotime($b['return_dt']))) ?></td>
      <td><?= e(money($b['total_rent'])) ?></td>
      <td><span class="badge b-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
      <td><span class="badge b-<?= e($b['return_status']) ?>"><?= e($b['return_status']) ?></span></td>
      <td><?php if ($b['status'] === 'confirmed'): ?><a class="btn btn-sm btn-ghost" href="invoice.php?id=<?= (int)$b['id'] ?>">View / Print</a><?php else: ?><span class="muted small">—</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">No history yet.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
