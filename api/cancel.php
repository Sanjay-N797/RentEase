<?php
/* Cancel a booking (owner customer, or admin). Promotes waitlist inside the same transaction. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'Method not allowed.']);
$user = current_user();
if (!$user) json_out(401, ['ok' => false, 'error' => 'Please log in.']);
if (!csrf_valid()) json_out(419, ['ok' => false, 'error' => 'Security token expired. Refresh the page.']);

$bid = (int)($_POST['booking_id'] ?? 0);
if ($bid < 1) json_out(422, ['ok' => false, 'error' => 'Invalid booking.']);

$db = pdo();
try {
    $db->beginTransaction();
    // lock the vehicle row first (same lock order as book.php) to avoid deadlocks
    $st = $db->prepare('SELECT vehicle_id FROM bookings WHERE id = ?');
    $st->execute([$bid]);
    $vid = $st->fetchColumn();
    if (!$vid) { $db->rollBack(); json_out(404, ['ok' => false, 'error' => 'Booking not found.']); }
    $db->prepare('SELECT id FROM vehicles WHERE id = ? FOR UPDATE')->execute([$vid]);

    $st = $db->prepare('SELECT b.*, v.cancel_policy FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id WHERE b.id = ? FOR UPDATE');
    $st->execute([$bid]);
    $b = $st->fetch();
    if (!$b || ($user['role'] !== 'admin' && (int)$b['user_id'] !== (int)$user['id'])) {
        $db->rollBack(); json_out(404, ['ok' => false, 'error' => 'Booking not found.']);
    }
    if ($user['role'] === 'admin') {
        if ($b['status'] === 'cancelled' || $b['return_status'] === 'returned') { $db->rollBack(); json_out(409, ['ok' => false, 'error' => 'This booking can no longer be cancelled.']); }
    } else {
        [$can, $why] = cancel_allowed($b);
        if (!$can) { $db->rollBack(); json_out(403, ['ok' => false, 'error' => "Cancellation not allowed: $why."]); }
    }
    $wasConfirmed = $b['status'] === 'confirmed';
    $db->prepare("UPDATE bookings SET status='cancelled' WHERE id = ?")->execute([$bid]);
    $promoted = $wasConfirmed ? promote_waitlist($db, (int)$vid) : [];
    $db->commit();
    $msg = 'Booking ' . $b['booking_code'] . ' cancelled.';
    if ($promoted) $msg .= ' Waitlist promoted: ' . implode(', ', $promoted) . '.';
    json_out(200, ['ok' => true, 'message' => $msg, 'promoted' => $promoted]);
} catch (PDOException $ex) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('cancel.php: ' . $ex->getMessage());
    json_out(500, ['ok' => false, 'error' => 'Could not cancel the booking. Please try again.']);
}
