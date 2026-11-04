<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('RENTEASESID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}
require_once __DIR__ . '/csrf.php';

/* ---------- small helpers ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p)
{
    header('Location: ' . (preg_match('#^https?://#', $p) ? $p : url($p)));
    exit;
}
function money($n): string { return '₹' . number_format((float)$n, 2); }
function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_get(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function valid_password(string $p): bool
{
    return strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p);
}
function json_out(int $code, array $data)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- session / user ---------- */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    // idle timeout 60 min
    if (!empty($_SESSION['last']) && time() - $_SESSION['last'] > 3600) {
        logout_user();
        session_start();
        return $cache = null;
    }
    $_SESSION['last'] = time();
    $st = pdo()->prepare('SELECT id,name,email,licence_number,city,licence_type,phone,dob,role,status FROM users WHERE id=?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'active') {
        logout_user();
        session_start();
        return $cache = null;
    }
    return $cache = $u;
}
function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid']  = (int)$u['id'];
    $_SESSION['role'] = $u['role'];
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
/** Gate a page to one role. Redirects to that role's login page otherwise. */
function require_role(string $role): array
{
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        redirect($role === 'admin' ? 'admin/login.php' : 'customer/login.php');
    }
    return $u;
}

/* ---------- brute-force lockout: 5 failures => 5 minute lock ---------- */
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }
function login_lock_remaining(string $ident): int
{
    $st = pdo()->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) s FROM login_attempts WHERE identifier=? AND ip=? AND success=2");
    $st->execute([$ident, client_ip()]);
    $s = $st->fetchColumn();
    return ($s !== null && $s !== false && (int)$s < 300) ? 300 - (int)$s : 0;
}
function login_record_failure(string $ident): void
{
    $db = pdo();
    $db->prepare("INSERT INTO login_attempts (identifier, ip, success) VALUES (?,?,0)")->execute([$ident, client_ip()]);
    $st = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier=? AND ip=? AND success=0 AND attempted_at > (NOW() - INTERVAL 5 MINUTE)");
    $st->execute([$ident, client_ip()]);
    if ((int)$st->fetchColumn() >= 5) {
        $db->prepare("INSERT INTO login_attempts (identifier, ip, success) VALUES (?,?,2)")->execute([$ident, client_ip()]);
    }
}
function login_clear(string $ident): void
{
    pdo()->prepare("DELETE FROM login_attempts WHERE identifier=? AND ip=?")->execute([$ident, client_ip()]);
}
/**
 * Shared login routine. $role 'customer' accepts licence or e-mail; 'admin' e-mail only.
 * Returns error string or null on success (after session is created).
 */
function attempt_login(string $ident, string $password, string $role): ?string
{
    $ident = trim($ident);
    if ($ident === '' || $password === '') return 'Please fill in all fields.';
    $key = strtolower($ident);
    if ($left = login_lock_remaining($key)) {
        return 'Too many failed attempts. Try again in ' . ceil($left / 60) . ' minute(s).';
    }
    if ($role === 'admin') {
        $st = pdo()->prepare("SELECT * FROM users WHERE role='admin' AND email=? LIMIT 1");
        $st->execute([strtolower($ident)]);
    } else {
        $st = pdo()->prepare("SELECT * FROM users WHERE role='customer' AND (email=? OR licence_number=?) LIMIT 1");
        $st->execute([strtolower($ident), strtoupper($ident)]);
    }
    $u = $st->fetch();
    $hash = $u['password_hash'] ?? password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash);
    if (!$u || !$ok) {
        login_record_failure($key);
        return 'Invalid credentials.';
    }
    if ($u['status'] !== 'active') return 'This account has been disabled. Contact the administrator.';
    login_clear($key);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    login_user($u);
    return null;
}
