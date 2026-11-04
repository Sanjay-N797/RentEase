<?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();
if ($u && $u['role'] === 'customer') redirect('customer/dashboard.php');
$errors = []; $f = ['name'=>'','email'=>'','licence_number'=>'','phone'=>'','city'=>'','licence_type'=>'LMV','dob'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $f['email'] = strtolower($f['email']);
    $f['licence_number'] = strtoupper(preg_replace('/\s+/', '', $f['licence_number']));
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');

    if (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100) $errors[] = 'Enter your full name (2–100 characters).';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 150) $errors[] = 'Enter a valid e-mail address.';
    if (!preg_match('/^[A-Z0-9\-\/]{8,30}$/', $f['licence_number'])) $errors[] = 'Licence number must be 8–30 letters, digits, - or /.';
    if (!preg_match('/^[0-9+\-\s]{10,15}$/', $f['phone'])) $errors[] = 'Enter a valid phone number (10–15 digits).';
    if ($f['city'] === '' || mb_strlen($f['city']) > 80) $errors[] = 'Enter your city.';
    if (!in_array($f['licence_type'], LICENCE_TYPES, true)) $errors[] = 'Choose a licence type.';
    $dobOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['dob']) && checkdate((int)substr($f['dob'],5,2),(int)substr($f['dob'],8,2),(int)substr($f['dob'],0,4));
    if (!$dobOk) $errors[] = 'Enter a valid date of birth.';
    elseif ((new DateTime($f['dob']))->diff(new DateTime('today'))->y < 18) $errors[] = 'You must be at least 18 years old.';
    if (!valid_password($pw)) $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            pdo()->prepare("INSERT INTO users (name,email,licence_number,password_hash,city,licence_type,phone,dob,role,status) VALUES (?,?,?,?,?,?,?,?, 'customer','active')")
                ->execute([$f['name'],$f['email'],$f['licence_number'],password_hash($pw, PASSWORD_DEFAULT),$f['city'],$f['licence_type'],$f['phone'],$f['dob']]);
            flash_set('success', 'Registration successful. Please log in.');
            redirect('customer/login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') $errors[] = 'An account with this e-mail or licence number already exists.';
            else { error_log($ex->getMessage()); $errors[] = 'Could not register. Please try again.'; }
        }
    }
}
$pageTitle = 'Register'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap wide"><div class="auth-card">
  <h1>Create your account</h1>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label for="name">Full name</label><input type="text" id="name" name="name" value="<?= e($f['name']) ?>" required></div>
      <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e($f['email']) ?>" required></div>
      <div class="field"><label for="licence_number">Driving licence number</label><input type="text" id="licence_number" name="licence_number" value="<?= e($f['licence_number']) ?>" required></div>
      <div class="field"><label for="licence_type">Licence type</label>
        <select id="licence_type" name="licence_type"><?php foreach (LICENCE_TYPES as $t): ?><option <?= $f['licence_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?= e($f['phone']) ?>" required></div>
      <div class="field"><label for="city">City</label><input type="text" id="city" name="city" value="<?= e($f['city']) ?>" required></div>
      <div class="field"><label for="dob">Date of birth</label><input type="date" id="dob" name="dob" value="<?= e($f['dob']) ?>" max="<?= e(date('Y-m-d', strtotime('-18 years'))) ?>" required></div>
      <div></div>
      <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required minlength="8">
        <div class="hint">Minimum 8 characters, with at least one letter and one number.</div></div>
      <div class="field"><label for="password2">Confirm password</label><input type="password" id="password2" name="password2" required></div>
    </div>
    <button class="btn btn-block btn-lg" type="submit">Register</button>
  </form>
  <div class="auth-links"><span>Already registered?</span><a href="login.php">Log in</a></div>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
