<?php
require_once __DIR__ . '/../includes/auth.php';
$sent = false; $link = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ident = trim($_POST['identifier'] ?? '');
    $db = pdo();
    $st = $db->prepare("SELECT id FROM users WHERE role='customer' AND status='active' AND (email=? OR licence_number=?) LIMIT 1");
    $st->execute([strtolower($ident), strtoupper($ident)]);
    if ($uid = $st->fetchColumn()) {
        $token = bin2hex(random_bytes(32));
        $db->prepare('UPDATE password_resets SET used=1 WHERE user_id=?')->execute([$uid]);
        $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)')
           ->execute([$uid, hash('sha256', $token)]);
        $scheme = (!empty($_SERVER['HTTPS']) ? 'https' : 'http');
        $link = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('customer/reset.php?token=' . $token);
        error_log('RentEase password reset link: ' . $link);   // swap for mail() in production
    }
    $sent = true;
}
$pageTitle = 'Forgot Password'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap"><div class="auth-card">
  <h1>Forgot password</h1>
  <?php if ($sent): ?>
    <div class="alert alert-success">If an account exists, a reset link valid for <b>1 hour</b> has been generated.</div>
    <?php if ($link && DEMO_SHOW_RESET_LINK): ?>
      <div class="alert alert-info"><b>Demo mode</b> (no mail server on localhost). Use this link:<br><a href="<?= e($link) ?>"><?= e($link) ?></a></div>
    <?php endif; ?>
    <a class="btn btn-ghost btn-block" href="login.php">Back to login</a>
  <?php else: ?>
    <p class="muted">Enter your licence number or e-mail to receive a reset link.</p>
    <form method="post"><?= csrf_field() ?>
      <div class="field"><label for="identifier">Licence number or e-mail</label><input type="text" id="identifier" name="identifier" required autofocus></div>
      <button class="btn btn-block btn-lg" type="submit">Generate reset link</button>
    </form>
    <div class="auth-links"><a href="login.php">Back to login</a></div>
  <?php endif; ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
