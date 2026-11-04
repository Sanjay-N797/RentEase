<?php
/* Live vehicle feed: JSON of PUBLISHED vehicles only. Polled by assets/js/app.js every 30 s. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$u = current_user();
if (!$u) json_out(401, ['ok' => false, 'error' => 'Please log in.']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(405, ['ok' => false, 'error' => 'Method not allowed.']);

$where  = ["v.status = 'published'", 'v.available_until >= CURDATE()'];
$params = [];

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $like = '%' . addcslashes(mb_substr($q, 0, 60), '%_\\') . '%';
    $where[] = '(v.name LIKE ? OR v.category LIKE ? OR v.pickup_location LIKE ? OR v.description LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
$cat = (string)($_GET['category'] ?? '');
if ($cat !== '' && in_array($cat, CATEGORIES, true)) { $where[] = 'v.category = ?'; $params[] = $cat; }
$fuel = (string)($_GET['fuel'] ?? '');
if ($fuel !== '' && in_array($fuel, FUEL_TYPES, true)) { $where[] = 'v.fuel_type = ?'; $params[] = $fuel; }

$from = (string)($_GET['from'] ?? '');
$to   = (string)($_GET['to'] ?? '');
$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
if ($validDate($from) && $validDate($to) && $from < $to) {
    // window covers the dates AND no confirmed booking overlaps them
    $where[] = 'v.available_from <= ? AND v.available_until >= ?';
    array_push($params, $from, $to);
    $where[] = "NOT EXISTS (SELECT 1 FROM bookings b WHERE b.vehicle_id = v.id AND b.status = 'confirmed' AND b.pickup_dt < ? AND b.return_dt > ?)";
    array_push($params, $to . ' ' . PICKUP_TIME, $from . ' ' . PICKUP_TIME);
}

$orders = [
    'newest'     => 'v.created_at DESC, v.id DESC',
    'price_asc'  => 'v.rent_per_day ASC',
    'price_desc' => 'v.rent_per_day DESC',
    'name'       => 'v.name ASC',
];
$order = $orders[$_GET['sort'] ?? 'newest'] ?? $orders['newest'];
$limit = isset($_GET['limit']) ? max(1, min(60, (int)$_GET['limit'])) : 60;

$sql = "SELECT v.id, v.name, v.category, v.photo, v.fuel_type, v.transmission, v.seats, v.rent_per_day,
               v.pickup_location, v.branch, v.available_from, v.available_until
        FROM vehicles v WHERE " . implode(' AND ', $where) . " ORDER BY $order LIMIT $limit";
$st = pdo()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['seats'] = (int)$r['seats'];
    $r['rent_per_day'] = (float)$r['rent_per_day'];
    $r['photo_url'] = photo_url($r['photo']);
    $r['detail_url'] = url('customer/details.php?id=' . $r['id']);
    unset($r['photo']);
}
unset($r);
json_out(200, ['ok' => true, 'generated_at' => date('c'), 'count' => count($rows), 'vehicles' => $rows]);
