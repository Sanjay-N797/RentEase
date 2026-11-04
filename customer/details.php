<?php
require_once __DIR__ . '/../includes/booking_lib.php';
require_once __DIR__ . '/../includes/upload.php';
$me = require_role('customer');
$id = (int)($_GET['id'] ?? 0);
$st = pdo()->prepare("SELECT * FROM vehicles WHERE id=? AND status='published'");
$st->execute([$id]);
$v = $st->fetch();
$pageTitle = $v ? $v['name'] : 'Vehicle not available'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
if (!$v): ?>
  <div class="card" style="text-align:center"><h1>Vehicle not available</h1><p class="muted">This vehicle is not published or no longer exists.</p><a class="btn" href="explore.php">Back to explore</a></div>
<?php include __DIR__ . '/../includes/footer.php'; exit; endif;

$elig = check_eligibility($me, $v);
$bk = pdo()->prepare("SELECT pickup_dt, return_dt FROM bookings WHERE vehicle_id=? AND status='confirmed' AND return_dt > NOW() ORDER BY pickup_dt");
$bk->execute([$id]);
$booked = $bk->fetchAll();
$photo = photo_url($v['photo']);
$today = date('Y-m-d');
$minDate = max($today, $v['available_from']);
?>
<p><a href="explore.php">← Back to explore</a></p>
<div class="detail">
  <div>
    <div class="detail-photo"><?php if ($photo): ?><img src="<?= e($photo) ?>" alt="<?= e($v['name']) ?>"><?php else: ?><div class="ph" style="font-size:6rem"><?= $v['category'] === 'Bike' ? '🏍️' : ($v['category'] === 'Van' ? '🚐' : '🚗') ?></div><?php endif; ?></div>
    <div class="card" style="margin-top:1rem">
      <h1><?= e($v['name']) ?></h1>
      <p><span class="badge b-published"><?= e($v['category']) ?></span></p>
      <p><?= nl2br(e($v['description'])) ?></p>
      <dl class="kv">
        <dt>Fuel</dt><dd><?= e($v['fuel_type']) ?></dd>
        <dt>Transmission</dt><dd><?= e($v['transmission']) ?></dd>
        <dt>Seats</dt><dd><?= (int)$v['seats'] ?></dd>
        <dt>Registration</dt><dd><?= e($v['reg_number']) ?></dd>
        <dt>Pickup location</dt><dd><?= e($v['pickup_location']) ?></dd>
        <dt>Branch</dt><dd><?= e($v['branch']) ?> <?= $v['branch_contact'] ? '· ' . e($v['branch_contact']) : '' ?></dd>
        <dt>Availability</dt><dd><?= e($v['available_from']) ?> → <?= e($v['available_until']) ?></dd>
        <dt>Eligibility</dt><dd><?= e($v['eligibility']) ?></dd>
        <dt>Minimum age</dt><dd><?= (int)$v['min_age'] ?> years</dd>
        <dt>Licence needed</dt><dd><?= $v['licence_restriction'] !== '' ? e(str_replace(',', ' / ', $v['licence_restriction'])) : 'Any' ?></dd>
        <dt>Cancellation</dt><dd><?= e(CANCEL_POLICIES[$v['cancel_policy']] ?? $v['cancel_policy']) ?></dd>
      </dl>
    </div>
  </div>
  <aside class="card" style="position:sticky;top:80px">
    <div class="price" style="font-size:1.8rem"><?= e(money($v['rent_per_day'])) ?><small> / day</small></div>
    <?php if ($elig): ?>
      <div class="alert alert-warning" style="margin-top:1rem"><?= e($elig) ?></div>
    <?php else: ?>
      <form id="bookForm" data-rent="<?= e($v['rent_per_day']) ?>" style="margin-top:1rem">
        <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
        <?= csrf_field() ?>
        <div class="field"><label for="pickup_date">Pickup date</label><input type="date" id="pickup_date" name="pickup_date" min="<?= e($minDate) ?>" max="<?= e($v['available_until']) ?>" required></div>
        <div class="field"><label for="return_date">Return date</label><input type="date" id="return_date" name="return_date" min="<?= e($minDate) ?>" max="<?= e($v['available_until']) ?>" required></div>
        <div id="bookSummary" class="small" style="margin-bottom:1rem"></div>
        <div id="bookMsg" class="alert" hidden></div>
        <button id="bookBtn" class="btn btn-teal btn-block btn-lg" type="submit">BOOK NOW</button>
      </form>
    <?php endif; ?>
    <?php if ($booked): ?>
      <h3 style="margin-top:1.3rem">Already booked</h3>
      <ul class="small muted" style="padding-left:1.1rem;margin:0">
        <?php foreach ($booked as $b): ?><li><?= e(date('d M Y', strtotime($b['pickup_dt']))) ?> → <?= e(date('d M Y', strtotime($b['return_dt']))) ?></li><?php endforeach; ?>
      </ul>
      <p class="hint">You can still request these dates and join the waitlist.</p>
    <?php endif; ?>
  </aside>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
