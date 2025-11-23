<?php
session_start();
require_once __DIR__ . '/db.php';
$pdo = db();

// Base path for links when hosted under a subfolder
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '.' || $base === '\\') {
  $base = '';
}
// If current script is inside /admin, lift base one level up so assets resolve from /public
if (basename($base) === 'admin') {
  $base = rtrim(dirname($base), '/\\');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $full_name = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role = 'customer';

  if ($username === '' || $password === '' || $full_name === '' || $phone === ''){
    $error = 'Full name, phone, username, and password are required.';
  } else {
    try {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
      $stmt->execute([$username]);
      if ($stmt->fetch()){
        $error = 'Username already exists.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, phone, email) VALUES (?,?,?,?,?,?)');
        $ins->execute([$username, $hash, $role, $full_name, $phone, $email ?: null]);
        $_SESSION['user'] = [ 'id'=>$pdo->lastInsertId(), 'username'=>$username, 'role'=>$role ];
        header('Location: ' . ($base ?: '/') . '/');
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Registration failed.';
    }
  }
}

include __DIR__ . '/partials/header.php';
?>
<!-- Background Slideshow -->
<div class="bbq-slideshow">
  <div class="slide slide-1"></div>
  <div class="slide slide-2"></div>
  <div class="slide slide-3"></div>
  <div class="slide slide-4"></div>
  <div class="slide slide-5"></div>
  <div class="slide slide-6"></div>
  <div class="bbq-overlay"></div>
</div>

<div class="row justify-content-center bbq-bg py-4">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <div class="text-center mb-2">
          <img src="<?= ($base ?: '/') ?>/images/roastrack.png" alt="RoastRack" width="96" height="96" class="logo-tilt"/>
        </div>
        <h4 class="mb-3 text-center">Sign up as a Customer</h4>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input class="form-control" name="full_name" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Email (optional)</label>
            <input type="email" class="form-control" name="email" />
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" autocomplete="username" />
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" autocomplete="new-password" />
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= $base ?>/login.php">Have an account? Login</a>
            <button class="btn btn-primary">Create account</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
