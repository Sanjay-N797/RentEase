<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('customer');
refresh_overdue();
$st = pdo()->prepare("SELECT b.*, v.name AS vname, v.pickup_location, v.cancel_policy, v.status AS vstatus
  FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id
  WHERE b.user_id=? AND b.status IN ('confirmed','waitlisted') AND b.return_status <> 'returned' AND b.return_dt >= NOW()
  ORDER BY b.pickup_dt ASC");
$st->execute([$me['id']]);
$rows = $st->fetchAll();
$pageTitle = 'My Bookings'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>My bookings</h1><p class="muted">Upcoming, active and waitlisted bookings.</p></div><a class="btn" href="explore.php">Book another vehicle</a></div>
<div class="table-wrap"><table>
  <thead><tr><th>Booking ID</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $b): [$can, $why] = cancel_allowed($b); ?>
    <tr>
      <td><b><?= e($b['booking_code']) ?></b></td>
      <td><?= e($b['vname']) ?><div class="small muted"><?= e($b['pickup_location']) ?></div></td>
      <td><?= e(date('d M Y, h:i A', strtotime($b['pickup_dt']))) ?></td>
      <td><?= e(date('d M Y, h:i A', strtotime($b['return_dt']))) ?></td>
      <td><?= e(money($b['total_rent'])) ?></td>
      <td><span class="badge b-<?= e($b['status']) ?>"><?= e($b['status']) ?></span><?php if ($b['return_status'] === 'overdue'): ?> <span class="badge b-overdue">overdue</span><?php endif; ?></td>
      <td class="actions">
        <?php if ($b['status'] === 'confirmed'): ?><a class="btn btn-sm btn-ghost" href="invoice.php?id=<?= (int)$b['id'] ?>">Invoice</a><?php endif; ?>
        <?php if ($can): ?><button class="btn btn-sm btn-danger js-cancel" data-id="<?= (int)$b['id'] ?>" data-code="<?= e($b['booking_code']) ?>">Cancel</button>
        <?php else: ?><span class="small muted" title="<?= e($why) ?>"><?= e($why) ?></span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">No upcoming bookings. <a href="explore.php">Explore vehicles</a></td></tr><?php endif; ?>
  </tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
