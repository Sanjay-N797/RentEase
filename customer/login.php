<?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();
if ($u && $u['role'] === 'customer') redirect('customer/dashboard.php');
$error = null; $ident = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ident = trim($_POST['identifier'] ?? '');
    $error = attempt_login($ident, $_POST['password'] ?? '', 'customer');
    if (!$error) redirect('customer/dashboard.php');
}
$pageTitle = 'Customer Login'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap"><div class="auth-card">
  <h1>Welcome back</h1>
  <p class="muted" style="text-align:center">Log in with your licence number or e-mail.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field"><label for="identifier">Licence number or e-mail</label>
      <input type="text" id="identifier" name="identifier" value="<?= e($ident) ?>" required autofocus></div>
    <div class="field"><label for="password">Password</label>
      <input type="password" id="password" name="password" required></div>
    <button class="btn btn-block btn-lg" type="submit">Log in</button>
  </form>
  <div class="auth-links"><a href="forgot.php">Forgot password?</a><a href="register.php">Create account</a></div>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
