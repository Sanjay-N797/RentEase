<?php
/* Create a booking. Transaction + SELECT ... FOR UPDATE on the vehicle row serialises concurrent bookings. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'Method not allowed.']);
$user = current_user();
if (!$user || $user['role'] !== 'customer') json_out(401, ['ok' => false, 'error' => 'Please log in as a customer.']);
if (!csrf_valid()) json_out(419, ['ok' => false, 'error' => 'Security token expired. Refresh the page.']);

$vid      = (int)($_POST['vehicle_id'] ?? 0);
$pickup   = (string)($_POST['pickup_date'] ?? '');
$return   = (string)($_POST['return_date'] ?? '');
$waitlist = ($_POST['waitlist'] ?? '') === '1';

$okDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
if ($vid < 1 || !$okDate($pickup) || !$okDate($return)) json_out(422, ['ok' => false, 'error' => 'Please choose valid pickup and return dates.']);
if ($pickup < date('Y-m-d')) json_out(422, ['ok' => false, 'error' => 'Pickup date cannot be in the past.']);
if ($return <= $pickup) json_out(422, ['ok' => false, 'error' => 'Return date must be after the pickup date.']);

$pickupDt = $pickup . ' ' . PICKUP_TIME;
$returnDt = $return . ' ' . PICKUP_TIME;
$days = (int)(new DateTime($pickup))->diff(new DateTime($return))->days;
if ($days > 60) json_out(422, ['ok' => false, 'error' => 'A single booking cannot exceed 60 days.']);

$db = pdo();
try {
    $db->beginTransaction();

    $st = $db->prepare('SELECT * FROM vehicles WHERE id = ? FOR UPDATE');   // row lock
    $st->execute([$vid]);
    $v = $st->fetch();
    if (!$v || $v['status'] !== 'published') { $db->rollBack(); json_out(409, ['ok' => false, 'error' => 'This vehicle is not available for booking.']); }

    if ($pickup < $v['available_from'] || $return > $v['available_until']) {
        $db->rollBack();
        json_out(422, ['ok' => false, 'error' => "Dates must be within the availability window ({$v['available_from']} to {$v['available_until']})."]);
    }
    if ($err = check_eligibility($user, $v)) { $db->rollBack(); json_out(403, ['ok' => false, 'error' => $err]); }

    // duplicate: this customer already holds a live booking on this vehicle for overlapping dates
    $st = $db->prepare("SELECT booking_code FROM bookings WHERE vehicle_id=? AND user_id=? AND status IN ('confirmed','waitlisted') AND pickup_dt < ? AND return_dt > ? LIMIT 1 FOR UPDATE");
    $st->execute([$vid, $user['id'], $returnDt, $pickupDt]);
    if ($dup = $st->fetchColumn()) { $db->rollBack(); json_out(409, ['ok' => false, 'error' => "You already have booking $dup for these dates."]); }

    // overlap with confirmed bookings
    $st = $db->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id=? AND status='confirmed' AND pickup_dt < ? AND return_dt > ? FOR UPDATE");
    $st->execute([$vid, $returnDt, $pickupDt]);
    $overlap = (int)$st->fetchColumn() > 0;
    if ($overlap && !$waitlist) {
        $db->rollBack();
        json_out(409, ['ok' => false, 'can_waitlist' => true, 'error' => 'The vehicle is already booked for these dates. Join the waitlist? You will be confirmed automatically if a booking is cancelled.']);
    }
    $status = $overlap ? 'waitlisted' : 'confirmed';
    $total = round($days * (float)$v['rent_per_day'], 2);
    $code = generate_booking_code($db);

    // A previously cancelled row with the same (vehicle,user,pickup) would violate the UNIQUE key: reuse it.
    $st = $db->prepare("SELECT id FROM bookings WHERE vehicle_id=? AND user_id=? AND pickup_dt=? AND status='cancelled' FOR UPDATE");
    $st->execute([$vid, $user['id'], $pickupDt]);
    if ($old = $st->fetchColumn()) {
        $db->prepare("UPDATE bookings SET booking_code=?, return_dt=?, total_rent=?, status=?, return_status='pending', created_at=NOW() WHERE id=?")
           ->execute([$code, $returnDt, $total, $status, $old]);
    } else {
        $db->prepare("INSERT INTO bookings (booking_code, vehicle_id, user_id, pickup_dt, return_dt, total_rent, status, return_status) VALUES (?,?,?,?,?,?,?, 'pending')")
           ->execute([$code, $vid, $user['id'], $pickupDt, $returnDt, $total, $status]);
    }
    $db->commit();
    json_out(200, [
        'ok' => true, 'booking_code' => $code, 'status' => $status, 'total_rent' => $total, 'days' => $days,
        'message' => $status === 'confirmed' ? "Booking confirmed! Your booking ID is $code." : "You are on the waitlist. Your reference is $code.",
        'redirect' => url('customer/bookings.php'),
    ]);
} catch (PDOException $ex) {
    if ($db->inTransaction()) $db->rollBack();
    if ($ex->getCode() === '23000') json_out(409, ['ok' => false, 'error' => 'You already have a booking starting at this time for this vehicle.']);
    error_log('book.php: ' . $ex->getMessage());
    json_out(500, ['ok' => false, 'error' => 'Could not complete the booking. Please try again.']);
}
