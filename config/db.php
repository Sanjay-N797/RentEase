<?php
/**
 * RentEase - configuration + PDO connection.
 * Edit the DB_* values if your MySQL credentials differ from XAMPP defaults.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'rentease_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Demo mode: no mail server on localhost, so the password-reset link is shown on screen.
   Set to false in production and send the link by e-mail instead. */
define('DEMO_SHOW_RESET_LINK', true);

date_default_timezone_set('Asia/Kolkata');

/* Auto-detect URL base (e.g. "/rentease" when placed in htdocs/rentease). */
if (!defined('BASE_URL')) {
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $proj = realpath(__DIR__ . '/..') ?: '';
    $root = str_replace('\\', '/', $root);
    $proj = str_replace('\\', '/', $proj);
    if ($root !== '' && stripos($proj, $root) === 0) {
        define('BASE_URL', rtrim(substr($proj, strlen($root)), '/'));
    } else {
        define('BASE_URL', '/rentease');
    }
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PICKUP_TIME', '10:00:00');          // pickup / return time of day
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB

const CATEGORIES      = ['Hatchback', 'Sedan', 'SUV', 'Luxury', 'Bike', 'Van'];
const FUEL_TYPES      = ['Petrol', 'Diesel', 'Electric', 'CNG', 'Hybrid'];
const TRANSMISSIONS   = ['Manual', 'Automatic'];
const LICENCE_TYPES   = ['LMV', 'MCWG', 'MCWOG', 'HMV'];
const VEHICLE_STATUSES = ['draft', 'published', 'booking_closed', 'rented_out', 'maintenance', 'archived'];
const CANCEL_POLICIES = [
    'flexible' => 'Free cancellation any time before pickup',
    'moderate' => 'Cancel at least 24 hours before pickup',
    'strict'   => 'Cancel at least 72 hours before pickup',
    'none'     => 'Non-cancellable once confirmed',
];

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            error_log('RentEase DB error: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Start MySQL in XAMPP and import sql/schema.sql and sql/seed.sql.');
        }
    }
    return $pdo;
}
