<?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();
if ($u && $u['role'] === 'admin') redirect('admin/dashboard.php');
$error = null; $ident = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ident = trim($_POST['email'] ?? '');
    $error = attempt_login($ident, $_POST['password'] ?? '', 'admin');
    if (!$error) redirect('admin/dashboard.php');
}
$pageTitle = 'Admin Login'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap"><div class="auth-card admin">
  <h1>Admin sign in</h1>
  <p class="muted" style="text-align:center">Authorised staff only.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Admin e-mail</label><input type="email" id="email" name="email" value="<?= e($ident) ?>" required autofocus></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
    <button class="btn btn-teal btn-block btn-lg" type="submit">Sign in</button>
  </form>
  <div class="auth-links"><a href="<?= e(url('customer/login.php')) ?>">← Customer login</a></div>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
