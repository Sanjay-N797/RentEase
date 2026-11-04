<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('customer');
$pageTitle = 'Explore Vehicles'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Explore vehicles</h1><p class="muted">Only published vehicles are shown. The list refreshes automatically.</p></div>
  <span class="live-status" id="feedStatus">Loading…</span></div>
<div class="card" style="margin-bottom:1rem">
  <div class="filters">
    <div class="field" style="flex:2"><label for="fSearch">Search</label><input type="search" id="fSearch" placeholder="Name, category, location…"></div>
    <div class="field"><label for="fFuel">Fuel</label><select id="fFuel"><option value="">Any fuel</option><?php foreach (FUEL_TYPES as $f): ?><option><?= e($f) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="fFrom">Pickup date</label><input type="date" id="fFrom" min="<?= e(date('Y-m-d')) ?>"></div>
    <div class="field"><label for="fTo">Return date</label><input type="date" id="fTo" min="<?= e(date('Y-m-d')) ?>"></div>
    <div class="field"><label for="fSort">Sort by</label><select id="fSort"><option value="newest">Newest</option><option value="price_asc">Price: low → high</option><option value="price_desc">Price: high → low</option><option value="name">Name A–Z</option></select></div>
    <button type="button" class="btn btn-ghost" id="fReset">Reset</button>
  </div>
  <div class="chips-row" style="margin:0">
    <button type="button" class="chip-filter active" data-cat="">All</button>
    <?php foreach (CATEGORIES as $c): ?><button type="button" class="chip-filter" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?>
  </div>
</div>
<div class="vgrid" id="vehicleGrid" data-limit="60"><div class="empty">Loading vehicles…</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
