<?php
session_start();
require_once __DIR__ . '/db.php';
$pdo = db();
// Base for redirects when hosted under subfolder
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '/' || $base === '\\' || $base === '.') {
  $base = '';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($username === '' || $password === ''){
    $error = 'Username and password are required.';
  } else {
    $stmt = $pdo->prepare('SELECT id, username, full_name, phone, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])){
      $error = 'Invalid credentials.';
    } else {
      $_SESSION['user'] = [
        'id'=>$user['id'],
        'username'=>$user['username'],
        'full_name'=>$user['full_name'] ?? null,
        'phone'=>$user['phone'] ?? null,
        'role'=>$user['role']
      ];
      header('Location: ' . ($base ?: '/') . '/');
      exit;
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
  <div class="col-lg-5 col-md-7">
    <div class="card shadow-sm bbq-card">
      <div class="card-body p-4">
        <div class="d-flex justify-content-center mb-2">
          <img src="<?= ($base ?: '/') ?>/images/roastrack.png" alt="RoastRack" width="110" height="110" class="logo-tilt"/>
        </div>
        <div class="text-center mb-2"><span class="bbq-badge"><i class="bi bi-fire me-1"></i> Fresh off the grill</span></div>
        <h3 class="mb-1 text-center bbq-title">Login to Proceed</h3>
        <p class="text-center text-muted mb-4">Smoky goodness awaits. Sign in to reserve your barbecue.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
          <div class="mb-3 input-icon">
            <i class="bi bi-person"></i>
            <label class="form-label">Username</label>
            <input class="form-control" name="username" autocomplete="username" />
          </div>
          <div class="mb-3 input-icon">
            <i class="bi bi-shield-lock"></i>
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" autocomplete="current-password" />
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= $base ?>/register.php">Create account</a>
            <button class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
          </div>
          <div class="divider mb-3"><span>or</span></div>
          <div class="text-center">
            <a class="btn btn-outline-secondary w-100" href="<?= $base ?>/google_login.php">
              <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="18" height="18" class="google-icon" alt="Google"/>
              Continue with Google
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
