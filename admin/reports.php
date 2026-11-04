<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('admin');
$db = pdo();
refresh_overdue();
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-d 00:00:00', strtotime('first day of next month'));
$daysInMonth = (int)date('t');

// utilisation: booked days of each vehicle inside the current month
$st = $db->prepare("SELECT v.id, v.name, v.reg_number, v.status,
   COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(b.return_dt, ?), GREATEST(b.pickup_dt, ?)))),0) AS booked_days,
   COUNT(b.id) AS bookings, COALESCE(SUM(b.total_rent),0) AS revenue
   FROM vehicles v LEFT JOIN bookings b ON b.vehicle_id=v.id AND b.status='confirmed' AND b.pickup_dt < ? AND b.return_dt > ?
   WHERE v.status <> 'archived' GROUP BY v.id, v.name, v.reg_number, v.status ORDER BY booked_days DESC, v.name");
$st->execute([$monthEnd, $monthStart, $monthEnd, $monthStart]);
$util = $st->fetchAll();
$byCat = $db->query("SELECT v.category, COUNT(b.id) AS n, COALESCE(SUM(b.total_rent),0) AS rev FROM vehicles v LEFT JOIN bookings b ON b.vehicle_id=v.id AND b.status='confirmed' GROUP BY v.category ORDER BY n DESC, v.category")->fetchAll();
$byCity = $db->query("SELECT u.city, COUNT(b.id) AS n, COALESCE(SUM(b.total_rent),0) AS rev FROM bookings b JOIN users u ON u.id=b.user_id WHERE b.status='confirmed' GROUP BY u.city ORDER BY n DESC, u.city")->fetchAll();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rentease_report_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Vehicle utilisation - ' . date('F Y')]);
    fputcsv($o, ['Vehicle','Reg. No.','Status','Booked days','Days in month','Utilisation %','Bookings','Revenue']);
    foreach ($util as $r) fputcsv($o, array_map('csv_cell', [$r['name'],$r['reg_number'],$r['status'],$r['booked_days'],$daysInMonth,round(min(100, $r['booked_days'] / $daysInMonth * 100), 1),$r['bookings'],$r['revenue']]));
    fputcsv($o, []); fputcsv($o, ['Bookings by category']); fputcsv($o, ['Category','Confirmed bookings','Revenue']);
    foreach ($byCat as $r) fputcsv($o, array_map('csv_cell', [$r['category'],$r['n'],$r['rev']]));
    fputcsv($o, []); fputcsv($o, ['Bookings by customer city']); fputcsv($o, ['City','Confirmed bookings','Revenue']);
    foreach ($byCity as $r) fputcsv($o, array_map('csv_cell', [$r['city'],$r['n'],$r['rev']]));
    fclose($o); exit;
}
$pageTitle = 'Reports'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
$maxCat = max(1, ...array_map(fn($r) => (int)$r['n'], $byCat ?: [['n' => 1]]));
$maxCity = max(1, ...array_map(fn($r) => (int)$r['n'], $byCity ?: [['n' => 1]]));
?>
<div class="page-head"><div><h1>Reports</h1><p class="muted">Utilisation for <?= e(date('F Y')) ?> (<?= $daysInMonth ?> days).</p></div><a class="btn btn-teal" href="?export=1">⬇ Export CSV</a></div>
<div class="card" style="margin-bottom:1.2rem">
  <h2>Vehicle utilisation</h2>
  <?php foreach ($util as $r): $pct = min(100, round($r['booked_days'] / $daysInMonth * 100)); ?>
    <div class="bar-row"><div><b><?= e($r['name']) ?></b><div class="small muted"><?= e($r['booked_days']) ?> / <?= $daysInMonth ?> days</div></div>
      <div class="bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $pct ?>%"></i></div><div><b><?= $pct ?>%</b></div></div>
  <?php endforeach; if (!$util) echo '<p class="muted">No vehicles.</p>'; ?>
</div>
<div class="grid cols-2">
  <div class="card"><h2>Bookings by category</h2>
    <?php foreach ($byCat as $r): ?><div class="bar-row"><div><?= e($r['category']) ?></div><div class="bar"><i style="width:<?= round($r['n'] / $maxCat * 100) ?>%"></i></div><div><b><?= (int)$r['n'] ?></b></div></div><?php endforeach; if (!$byCat) echo '<p class="muted">No data.</p>'; ?>
  </div>
  <div class="card"><h2>Bookings by customer city</h2>
    <?php foreach ($byCity as $r): ?><div class="bar-row"><div><?= e($r['city']) ?></div><div class="bar"><i style="width:<?= round($r['n'] / $maxCity * 100) ?>%"></i></div><div><b><?= (int)$r['n'] ?></b></div></div><?php endforeach; if (!$byCity) echo '<p class="muted">No data yet.</p>'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
