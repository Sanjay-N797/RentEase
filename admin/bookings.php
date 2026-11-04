<?php
require_once __DIR__ . '/../includes/booking_lib.php';
$me = require_role('admin');
$db = pdo();
refresh_overdue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if (($_POST['action'] ?? '') === 'return') {
        $id = (int)($_POST['id'] ?? 0);
        $n = $db->prepare("UPDATE bookings SET return_status='returned' WHERE id=? AND status='confirmed' AND return_status<>'returned'");
        $n->execute([$id]);
        flash_set($n->rowCount() ? 'success' : 'warning', $n->rowCount() ? 'Vehicle marked as returned.' : 'Nothing to update.');
    }
    redirect('admin/bookings.php' . (!empty($_POST['qs']) ? '?' . preg_replace('/[^A-Za-z0-9=&_%+\-.]/', '', $_POST['qs']) : ''));
}

$q = trim($_GET['q'] ?? ''); $status = $_GET['status'] ?? ''; $rs = $_GET['return_status'] ?? '';
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$where = ['1=1']; $p = [];
if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $where[] = '(b.booking_code LIKE ? OR u.name LIKE ? OR u.licence_number LIKE ? OR v.name LIKE ? OR v.reg_number LIKE ?)'; array_push($p, $like, $like, $like, $like, $like); }
if (in_array($status, ['confirmed','waitlisted','cancelled'], true)) { $where[] = 'b.status=?'; $p[] = $status; }
if (in_array($rs, ['pending','returned','overdue'], true)) { $where[] = 'b.return_status=?'; $p[] = $rs; }
$isD = fn($x) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $x);
if ($isD($from)) { $where[] = 'b.pickup_dt >= ?'; $p[] = $from . ' 00:00:00'; }
if ($isD($to))   { $where[] = 'b.pickup_dt <= ?'; $p[] = $to . ' 23:59:59'; }
$sql = "SELECT b.*, v.name vname, v.reg_number, u.name uname, u.email, u.phone, u.licence_number
        FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id JOIN users u ON u.id=b.user_id
        WHERE " . implode(' AND ', $where) . " ORDER BY b.created_at DESC, b.id DESC";
$st = $db->prepare($sql); $st->execute($p); $rows = $st->fetchAll();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rentease_bookings_' . date('Ymd_His') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Booking ID','Vehicle','Reg. No.','Customer','Email','Phone','Licence','Pickup','Return','Total Rent','Status','Return Status','Created']);
    foreach ($rows as $r) fputcsv($o, array_map('csv_cell', [$r['booking_code'],$r['vname'],$r['reg_number'],$r['uname'],$r['email'],$r['phone'],$r['licence_number'],$r['pickup_dt'],$r['return_dt'],$r['total_rent'],$r['status'],$r['return_status'],$r['created_at']]));
    fclose($o); exit;
}
$qs = $_SERVER['QUERY_STRING'] ?? '';
$exportQs = http_build_query(array_filter(['q'=>$q,'status'=>$status,'return_status'=>$rs,'from'=>$from,'to'=>$to]) + ['export'=>1]);
$pageTitle = 'Manage Bookings'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Bookings</h1><p class="muted"><?= count($rows) ?> record(s)</p></div><a class="btn btn-teal" href="?<?= e($exportQs) ?>">⬇ Export CSV</a></div>
<form class="filters card" method="get">
  <div class="field" style="flex:2"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Booking ID, customer, licence, vehicle"></div>
  <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (['confirmed','waitlisted','cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="return_status">Return</label><select id="return_status" name="return_status"><option value="">All</option><?php foreach (['pending','returned','overdue'] as $s): ?><option <?= $rs === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="from">Pickup from</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label for="to">Pickup to</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <button class="btn" type="submit">Filter</button><a class="btn btn-ghost" href="bookings.php">Reset</a>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Booking ID</th><th>Vehicle</th><th>Customer</th><th>Pickup</th><th>Return</th><th>Total</th><th>Status</th><th>Return</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $b): ?><tr>
    <td><b><?= e($b['booking_code']) ?></b></td>
    <td><?= e($b['vname']) ?><div class="small muted"><?= e($b['reg_number']) ?></div></td>
    <td><?= e($b['uname']) ?><div class="small muted"><?= e($b['licence_number']) ?> · <?= e($b['phone']) ?></div></td>
    <td><?= e(date('d M Y', strtotime($b['pickup_dt']))) ?></td><td><?= e(date('d M Y', strtotime($b['return_dt']))) ?></td>
    <td><?= e(money($b['total_rent'])) ?></td>
    <td><span class="badge b-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
    <td><span class="badge b-<?= e($b['return_status']) ?>"><?= e($b['return_status']) ?></span></td>
    <td class="actions">
      <?php if ($b['status'] === 'confirmed' && $b['return_status'] !== 'returned'): ?>
        <form method="post" class="inline-form" data-confirm="Mark this vehicle as returned?"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>"><button class="btn btn-sm btn-teal" type="submit">Mark returned</button></form>
      <?php endif; ?>
      <?php if ($b['status'] !== 'cancelled' && $b['return_status'] !== 'returned'): ?><button class="btn btn-sm btn-danger js-cancel" data-id="<?= (int)$b['id'] ?>" data-code="<?= e($b['booking_code']) ?>">Cancel</button><?php endif; ?>
    </td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:2rem">No bookings found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
