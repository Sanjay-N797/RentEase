<?php
require_once __DIR__ . '/../includes/auth.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$db = pdo(); $row = null; $error = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = $db->prepare('SELECT id, user_id FROM password_resets WHERE token_hash=? AND used=0 AND expires_at > NOW() LIMIT 1');
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
}
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if (!valid_password($pw)) $error = 'Password must be at least 8 characters and contain a letter and a number.';
    elseif ($pw !== $pw2) $error = 'Passwords do not match.';
    else {
        $db->beginTransaction();
        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
        $db->prepare('UPDATE password_resets SET used=1 WHERE user_id=?')->execute([$row['user_id']]);
        $db->prepare('DELETE FROM login_attempts')->execute();   // clear lockouts so the user can sign in
        $db->commit();
        flash_set('success', 'Password updated. You can now log in.');
        redirect('customer/login.php');
    }
}
$pageTitle = 'Reset Password'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap"><div class="auth-card">
  <h1>Reset password</h1>
  <?php if (!$row): ?>
    <div class="alert alert-danger">This reset link is invalid, already used or has expired.</div>
    <a class="btn btn-block" href="forgot.php">Request a new link</a>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" required minlength="8"></div>
      <div class="field"><label for="password2">Confirm new password</label><input type="password" id="password2" name="password2" required></div>
      <button class="btn btn-block btn-lg" type="submit">Update password</button>
    </form>
  <?php endif; ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
