<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('customer');
$errP = []; $errW = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $db = pdo();
    if (($_POST['form'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $city = trim($_POST['city'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errP[] = 'Enter your full name.';
        if (!preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) $errP[] = 'Enter a valid phone number.';
        if ($city === '' || mb_strlen($city) > 80) $errP[] = 'Enter your city.';
        if (!$errP) {
            $db->prepare('UPDATE users SET name=?, phone=?, city=? WHERE id=?')->execute([$name, $phone, $city, $me['id']]);
            flash_set('success', 'Profile updated.'); redirect('customer/profile.php');
        }
    } elseif (($_POST['form'] ?? '') === 'password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['password'] ?? ''); $new2 = (string)($_POST['password2'] ?? '');
        $st = $db->prepare('SELECT password_hash FROM users WHERE id=?'); $st->execute([$me['id']]);
        if (!password_verify($cur, (string)$st->fetchColumn())) $errW[] = 'Current password is incorrect.';
        if (!valid_password($new)) $errW[] = 'New password must be at least 8 characters with a letter and a number.';
        if ($new !== $new2) $errW[] = 'New passwords do not match.';
        if (!$errW) {
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash_set('success', 'Password changed.'); redirect('customer/profile.php');
        }
    }
}
$pageTitle = 'Profile'; $area = 'customer';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>My profile</h1></div>
<div class="grid cols-2">
  <div class="card">
    <h2>Personal details</h2>
    <?php foreach ($errP as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="profile">
      <div class="field"><label for="name">Full name</label><input type="text" id="name" name="name" value="<?= e($_POST['name'] ?? $me['name']) ?>" required></div>
      <div class="field"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? $me['phone']) ?>" required></div>
      <div class="field"><label for="city">City</label><input type="text" id="city" name="city" value="<?= e($_POST['city'] ?? $me['city']) ?>" required></div>
      <div class="field"><label>E-mail</label><input type="text" value="<?= e($me['email']) ?>" disabled></div>
      <div class="field"><label>Licence number / type</label><input type="text" value="<?= e($me['licence_number'] . ' · ' . $me['licence_type']) ?>" disabled></div>
      <button class="btn" type="submit">Save changes</button>
    </form>
  </div>
  <div class="card">
    <h2>Change password</h2>
    <?php foreach ($errW as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="form" value="password">
      <div class="field"><label for="current">Current password</label><input type="password" id="current" name="current" required></div>
      <div class="field"><label for="p1">New password</label><input type="password" id="p1" name="password" required minlength="8"><div class="hint">Min 8 characters, at least one letter and one number.</div></div>
      <div class="field"><label for="p2">Confirm new password</label><input type="password" id="p2" name="password2" required></div>
      <button class="btn btn-navy" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
