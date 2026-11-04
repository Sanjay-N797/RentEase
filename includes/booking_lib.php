<?php
require_once __DIR__ . '/auth.php';

/** Mark confirmed, not-yet-returned bookings whose return time passed as overdue. */
function refresh_overdue(): void
{
    pdo()->exec("UPDATE bookings SET return_status='overdue' WHERE status='confirmed' AND return_status='pending' AND return_dt < NOW()");
}

/** Unique, human friendly booking code. */
function generate_booking_code(PDO $db): string
{
    $st = $db->prepare('SELECT 1 FROM bookings WHERE booking_code=?');
    do {
        $code = 'RE' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $st->execute([$code]);
    } while ($st->fetchColumn());
    return $code;
}

/** Licence / age eligibility. Returns error text or null if eligible. */
function check_eligibility(array $user, array $veh): ?string
{
    $min = (int)($veh['min_age'] ?? 0);
    if ($min > 0) {
        if (empty($user['dob'])) return "This vehicle needs a minimum age of $min; please update your date of birth.";
        $age = (new DateTime($user['dob']))->diff(new DateTime('today'))->y;
        if ($age < $min) return "Minimum age for this vehicle is $min years.";
    }
    $res = trim((string)($veh['licence_restriction'] ?? ''));
    if ($res !== '') {
        $allowed = array_map('trim', explode(',', $res));
        if (!in_array($user['licence_type'], $allowed, true)) {
            return 'This vehicle requires a ' . implode(' / ', $allowed) . ' licence (yours: ' . $user['licence_type'] . ').';
        }
    }
    return null;
}

/** Can this booking row (joined with cancel_policy) be cancelled by a customer? [bool, reason] */
function cancel_allowed(array $b): array
{
    if ($b['status'] === 'cancelled') return [false, 'Already cancelled'];
    if (($b['return_status'] ?? 'pending') !== 'pending') return [false, 'Vehicle already returned'];
    if ($b['status'] === 'waitlisted') return [true, ''];
    $left = strtotime($b['pickup_dt']) - time();
    if ($left <= 0) return [false, 'Rental has already started'];
    switch ($b['cancel_policy'] ?? 'flexible') {
        case 'none':     return [false, 'Non-cancellable booking'];
        case 'moderate': return $left >= 86400  ? [true, ''] : [false, 'Less than 24 hours to pickup'];
        case 'strict':   return $left >= 259200 ? [true, ''] : [false, 'Less than 72 hours to pickup'];
        default:         return [true, ''];
    }
}

/**
 * Promote waitlisted bookings (oldest first) that now fit. Must run inside a transaction
 * that already holds the vehicle row lock. Returns promoted booking codes.
 */
function promote_waitlist(PDO $db, int $vehicleId): array
{
    $promoted = [];
    $v = $db->prepare('SELECT status FROM vehicles WHERE id=?');
    $v->execute([$vehicleId]);
    if ($v->fetchColumn() !== 'published') return $promoted;

    $wl = $db->prepare("SELECT id, booking_code, pickup_dt, return_dt FROM bookings
                        WHERE vehicle_id=? AND status='waitlisted' AND pickup_dt > NOW()
                        ORDER BY created_at ASC, id ASC FOR UPDATE");
    $wl->execute([$vehicleId]);
    $chk = $db->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id=? AND status='confirmed' AND pickup_dt < ? AND return_dt > ?");
    $up  = $db->prepare("UPDATE bookings SET status='confirmed' WHERE id=?");
    foreach ($wl->fetchAll() as $w) {
        $chk->execute([$vehicleId, $w['return_dt'], $w['pickup_dt']]);
        if ((int)$chk->fetchColumn() === 0) {
            $up->execute([$w['id']]);
            $promoted[] = $w['booking_code'];
        }
    }
    return $promoted;
}

function csv_cell($v): string
{
    $v = (string)$v;
    if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) $v = "'" . $v;   // CSV-injection guard
    return $v;
}
