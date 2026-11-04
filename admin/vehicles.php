<?php
require_once __DIR__ . '/../includes/booking_lib.php';
require_once __DIR__ . '/../includes/upload.php';
$me = require_role('admin');
$db = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map = ['publish' => 'published', 'unpublish' => 'draft', 'maintenance' => 'maintenance', 'close' => 'booking_closed', 'rented' => 'rented_out', 'archive' => 'archived'];
    $st = $db->prepare('SELECT id, name, photo FROM vehicles WHERE id=?'); $st->execute([$id]);
    $v = $st->fetch();
    if (!$v) { flash_set('danger', 'Vehicle not found.'); }
    elseif (isset($map[$action])) {
        $db->beginTransaction();
        $db->prepare('SELECT id FROM vehicles WHERE id=? FOR UPDATE')->execute([$id]);
        $db->prepare('UPDATE vehicles SET status=? WHERE id=?')->execute([$map[$action], $id]);
        if ($action === 'publish') promote_waitlist($db, $id);
        $db->commit();
        flash_set('success', $v['name'] . ' → ' . str_replace('_', ' ', $map[$action]) . '.');
    } elseif ($action === 'delete') {
        $c = $db->prepare('SELECT COUNT(*) FROM bookings WHERE vehicle_id=?'); $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) flash_set('warning', 'Cannot delete a vehicle that has bookings. Archive it instead.');
        else {
            $db->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
            delete_photo($v['photo']);
            flash_set('success', 'Vehicle deleted.');
        }
    }
    redirect('admin/vehicles.php' . (!empty($_POST['qs']) ? '?' . preg_replace('/[^A-Za-z0-9=&_%+\-.]/', '', $_POST['qs']) : ''));
}

$q = trim($_GET['q'] ?? ''); $status = $_GET['status'] ?? ''; $cat = $_GET['category'] ?? '';
$where = ['1=1']; $p = [];
if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $where[] = '(name LIKE ? OR reg_number LIKE ? OR pickup_location LIKE ? OR branch LIKE ?)'; array_push($p, $like, $like, $like, $like); }
if (in_array($status, VEHICLE_STATUSES, true)) { $where[] = 'status=?'; $p[] = $status; }
if (in_array($cat, CATEGORIES, true)) { $where[] = 'category=?'; $p[] = $cat; }
$st = $db->prepare('SELECT * FROM vehicles WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, id DESC');
$st->execute($p);
$rows = $st->fetchAll();
$qs = $_SERVER['QUERY_STRING'] ?? '';
$pageTitle = 'Manage Vehicles'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
function act(string $a, int $id, string $label, string $cls, string $qs, string $confirm = ''): string {
    return '<form method="post" class="inline-form"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() .
      '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($a) . '"><input type="hidden" name="qs" value="' . e($qs) . '">' .
      '<button class="btn btn-sm ' . $cls . '" type="submit">' . e($label) . '</button></form>';
}
?>
<div class="page-head"><div><h1>Manage vehicles</h1><p class="muted"><?= count($rows) ?> vehicle(s)</p></div><a class="btn" href="vehicle_form.php">+ Add vehicle</a></div>
<form class="filters card" method="get">
  <div class="field" style="flex:2"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, reg. number, location, branch"></div>
  <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (VEHICLE_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All</option><?php foreach (CATEGORIES as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">Filter</button><a class="btn btn-ghost" href="vehicles.php">Reset</a>
</form>
<div class="table-wrap"><table>
  <thead><tr><th></th><th>Vehicle</th><th>Reg. no.</th><th>Rent/day</th><th>Window</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $v): $img = photo_url($v['photo']); ?>
  <tr>
    <td><?php if ($img): ?><img class="thumb" src="<?= e($img) ?>" alt=""><?php else: ?><span class="thumb">🚗</span><?php endif; ?></td>
    <td><b><?= e($v['name']) ?></b><div class="small muted"><?= e($v['category']) ?> · <?= e($v['fuel_type']) ?> · <?= e($v['branch']) ?></div></td>
    <td><?= e($v['reg_number']) ?></td><td><?= e(money($v['rent_per_day'])) ?></td>
    <td class="small"><?= e($v['available_from']) ?><br>→ <?= e($v['available_until']) ?></td>
    <td><span class="badge b-<?= e($v['status']) ?>"><?= e(str_replace('_', ' ', $v['status'])) ?></span></td>
    <td class="actions">
      <a class="btn btn-sm btn-ghost" href="vehicle_form.php?id=<?= (int)$v['id'] ?>">Edit</a>
      <?php if ($v['status'] !== 'published'): ?><?= act('publish', (int)$v['id'], 'Publish', 'btn-teal', $qs) ?><?php else: ?><?= act('unpublish', (int)$v['id'], 'Unpublish', 'btn-navy', $qs) ?><?php endif; ?>
      <?php if ($v['status'] !== 'maintenance'): ?><?= act('maintenance', (int)$v['id'], 'Maintenance', 'btn-ghost', $qs) ?><?php endif; ?>
      <?php if ($v['status'] === 'published'): ?><?= act('close', (int)$v['id'], 'Close bookings', 'btn-ghost', $qs) ?><?php endif; ?>
      <?php if ($v['status'] !== 'archived'): ?><?= act('archive', (int)$v['id'], 'Archive', 'btn-ghost', $qs, 'Archive this vehicle? It will be hidden from customers.') ?><?php endif; ?>
      <?= act('delete', (int)$v['id'], 'Delete', 'btn-danger', $qs, 'Permanently delete this vehicle? This cannot be undone.') ?>
    </td>
  </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">No vehicles found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
