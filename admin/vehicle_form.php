<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
$me = require_role('admin');
$db = pdo();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$v = null;
if ($id) {
    $st = $db->prepare('SELECT * FROM vehicles WHERE id=?'); $st->execute([$id]);
    $v = $st->fetch();
    if (!$v) { flash_set('danger', 'Vehicle not found.'); redirect('admin/vehicles.php'); }
}
$d = $v ?: ['name'=>'','description'=>'','category'=>'Hatchback','photo'=>'','reg_number'=>'','fuel_type'=>'Petrol','transmission'=>'Manual','seats'=>5,'rent_per_day'=>'',
  'pickup_location'=>'','branch'=>'','available_from'=>date('Y-m-d'),'available_until'=>date('Y-m-d', strtotime('+90 days')),'eligibility'=>'Any valid driving licence','min_age'=>18,
  'licence_restriction'=>'','branch_contact'=>'','cancel_policy'=>'flexible','status'=>'draft'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach (['name','description','category','reg_number','fuel_type','transmission','pickup_location','branch','available_from','available_until','eligibility','branch_contact','cancel_policy','status'] as $k)
        $d[$k] = trim((string)($_POST[$k] ?? ''));
    $d['reg_number'] = strtoupper(preg_replace('/\s+/', '', $d['reg_number']));
    $d['seats'] = (int)($_POST['seats'] ?? 0);
    $d['min_age'] = (int)($_POST['min_age'] ?? 18);
    $d['rent_per_day'] = trim((string)($_POST['rent_per_day'] ?? ''));
    $lr = array_values(array_intersect(LICENCE_TYPES, (array)($_POST['licence_restriction'] ?? [])));
    $d['licence_restriction'] = implode(',', $lr);

    $isDate = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) && checkdate((int)substr($x,5,2),(int)substr($x,8,2),(int)substr($x,0,4));
    if ($d['name'] === '' || mb_strlen($d['name']) > 120) $errors[] = 'Vehicle name is required (max 120 characters).';
    if (mb_strlen($d['description']) > 2000) $errors[] = 'Description is too long.';
    if (!in_array($d['category'], CATEGORIES, true)) $errors[] = 'Choose a valid category.';
    if (!preg_match('/^[A-Z0-9\-]{4,20}$/', $d['reg_number'])) $errors[] = 'Registration number must be 4–20 letters/digits.';
    if (!in_array($d['fuel_type'], FUEL_TYPES, true)) $errors[] = 'Choose a fuel type.';
    if (!in_array($d['transmission'], TRANSMISSIONS, true)) $errors[] = 'Choose a transmission.';
    if ($d['seats'] < 1 || $d['seats'] > 60) $errors[] = 'Seats must be between 1 and 60.';
    if (!is_numeric($d['rent_per_day']) || (float)$d['rent_per_day'] <= 0 || (float)$d['rent_per_day'] > 1000000) $errors[] = 'Enter a valid rent per day.';
    if ($d['pickup_location'] === '' || $d['branch'] === '') $errors[] = 'Pickup location and branch are required.';
    if (!$isDate($d['available_from']) || !$isDate($d['available_until'])) $errors[] = 'Enter valid availability dates.';
    elseif ($d['available_until'] < $d['available_from']) $errors[] = 'Available-until must not be before available-from.';
    if ($d['min_age'] < 16 || $d['min_age'] > 80) $errors[] = 'Minimum age must be between 16 and 80.';
    if (!isset(CANCEL_POLICIES[$d['cancel_policy']])) $errors[] = 'Choose a cancellation policy.';
    if (!in_array($d['status'], VEHICLE_STATUSES, true)) $errors[] = 'Choose a valid status.';
    if (mb_strlen($d['eligibility']) > 255 || mb_strlen($d['branch_contact']) > 60) $errors[] = 'Eligibility / contact text is too long.';

    $newPhoto = handle_photo_upload($_FILES['photo'] ?? [], $upErr);
    if ($newPhoto === null) $errors[] = $upErr;

    if (!$errors) {
        try {
            if ($v) {
                $photo = $newPhoto !== '' ? $newPhoto : $v['photo'];
                $db->prepare('UPDATE vehicles SET name=?,description=?,category=?,photo=?,reg_number=?,fuel_type=?,transmission=?,seats=?,rent_per_day=?,pickup_location=?,branch=?,available_from=?,available_until=?,eligibility=?,min_age=?,licence_restriction=?,branch_contact=?,cancel_policy=?,status=? WHERE id=?')
                   ->execute([$d['name'],$d['description'],$d['category'],$photo,$d['reg_number'],$d['fuel_type'],$d['transmission'],$d['seats'],$d['rent_per_day'],$d['pickup_location'],$d['branch'],$d['available_from'],$d['available_until'],$d['eligibility'],$d['min_age'],$d['licence_restriction'],$d['branch_contact'],$d['cancel_policy'],$d['status'],$v['id']]);
                if ($newPhoto !== '') delete_photo($v['photo']);
                flash_set('success', 'Vehicle updated.');
            } else {
                $db->prepare('INSERT INTO vehicles (name,description,category,photo,reg_number,fuel_type,transmission,seats,rent_per_day,pickup_location,branch,available_from,available_until,eligibility,min_age,licence_restriction,branch_contact,cancel_policy,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$d['name'],$d['description'],$d['category'],$newPhoto !== '' ? $newPhoto : null,$d['reg_number'],$d['fuel_type'],$d['transmission'],$d['seats'],$d['rent_per_day'],$d['pickup_location'],$d['branch'],$d['available_from'],$d['available_until'],$d['eligibility'],$d['min_age'],$d['licence_restriction'],$d['branch_contact'],$d['cancel_policy'],$d['status'],$me['id']]);
                flash_set('success', 'Vehicle created' . ($d['status'] === 'published' ? ' and published – it is now live for customers.' : ' as ' . $d['status'] . '.'));
            }
            redirect('admin/vehicles.php');
        } catch (PDOException $ex) {
            if ($newPhoto) delete_photo($newPhoto);
            if ($ex->getCode() === '23000') $errors[] = 'A vehicle with this registration number already exists.';
            else { error_log($ex->getMessage()); $errors[] = 'Database error while saving.'; }
        }
    } elseif ($newPhoto) { delete_photo($newPhoto); }
}
$selLic = array_filter(explode(',', (string)$d['licence_restriction']));
$pageTitle = $v ? 'Edit Vehicle' : 'Add Vehicle'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
function opt(array $list, string $cur): string { $o = ''; foreach ($list as $x) $o .= '<option ' . ($x === $cur ? 'selected' : '') . '>' . e($x) . '</option>'; return $o; }
?>
<div class="page-head"><h1><?= $v ? 'Edit vehicle' : 'Add vehicle' ?></h1><a href="vehicles.php">← Back to vehicles</a></div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-grid">
    <div class="field"><label for="name">Vehicle name *</label><input type="text" id="name" name="name" value="<?= e($d['name']) ?>" required maxlength="120"></div>
    <div class="field"><label for="reg_number">Registration number *</label><input type="text" id="reg_number" name="reg_number" value="<?= e($d['reg_number']) ?>" required></div>
    <div class="field full"><label for="description">Description</label><textarea id="description" name="description" maxlength="2000"><?= e($d['description']) ?></textarea></div>
    <div class="field"><label for="category">Category *</label><select id="category" name="category"><?= opt(CATEGORIES, $d['category']) ?></select></div>
    <div class="field"><label for="fuel_type">Fuel type *</label><select id="fuel_type" name="fuel_type"><?= opt(FUEL_TYPES, $d['fuel_type']) ?></select></div>
    <div class="field"><label for="transmission">Transmission *</label><select id="transmission" name="transmission"><?= opt(TRANSMISSIONS, $d['transmission']) ?></select></div>
    <div class="field"><label for="seats">Seats *</label><input type="number" id="seats" name="seats" min="1" max="60" value="<?= e($d['seats']) ?>" required></div>
    <div class="field"><label for="rent_per_day">Rent per day (₹) *</label><input type="number" id="rent_per_day" name="rent_per_day" min="1" step="0.01" value="<?= e($d['rent_per_day']) ?>" required></div>
    <div class="field"><label for="photo">Photo (JPG/PNG/WEBP, max 2 MB)</label><input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
      <?php if ($v && $v['photo']): ?><div class="hint">Current photo kept unless you upload a new one. <img class="thumb" src="<?= e(photo_url($v['photo'])) ?>" alt=""></div><?php endif; ?></div>
    <div class="field"><label for="pickup_location">Pickup location *</label><input type="text" id="pickup_location" name="pickup_location" value="<?= e($d['pickup_location']) ?>" required></div>
    <div class="field"><label for="branch">Owning branch *</label><input type="text" id="branch" name="branch" value="<?= e($d['branch']) ?>" required></div>
    <div class="field"><label for="branch_contact">Branch contact</label><input type="text" id="branch_contact" name="branch_contact" value="<?= e($d['branch_contact']) ?>"></div>
    <div class="field"><label for="available_from">Available from *</label><input type="date" id="available_from" name="available_from" value="<?= e($d['available_from']) ?>" required></div>
    <div class="field"><label for="available_until">Available until *</label><input type="date" id="available_until" name="available_until" value="<?= e($d['available_until']) ?>" required></div>
    <div class="field full"><label for="eligibility">Eligibility (shown to customers)</label><input type="text" id="eligibility" name="eligibility" value="<?= e($d['eligibility']) ?>" maxlength="255"></div>
    <div class="field"><label for="min_age">Minimum age *</label><input type="number" id="min_age" name="min_age" min="16" max="80" value="<?= e($d['min_age']) ?>" required></div>
    <div class="field"><label>Licence restriction <span class="muted">(none ticked = any)</span></label>
      <div style="display:flex;gap:1rem;flex-wrap:wrap"><?php foreach (LICENCE_TYPES as $lt): ?><label style="font-weight:500"><input type="checkbox" name="licence_restriction[]" value="<?= e($lt) ?>" <?= in_array($lt, $selLic, true) ? 'checked' : '' ?>> <?= e($lt) ?></label><?php endforeach; ?></div></div>
    <div class="field"><label for="cancel_policy">Cancellation policy *</label><select id="cancel_policy" name="cancel_policy"><?php foreach (CANCEL_POLICIES as $k => $label): ?><option value="<?= e($k) ?>" <?= $d['cancel_policy'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="status">Status *</label><select id="status" name="status"><?php foreach (VEHICLE_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select>
      <div class="hint">Only “published” vehicles appear to customers.</div></div>
  </div>
  <button class="btn btn-lg" type="submit"><?= $v ? 'Save changes' : 'Create vehicle' ?></button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
