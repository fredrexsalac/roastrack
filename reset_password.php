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

$message = '';
$error = '';
$token = $_GET['token'] ?? '';

// Verify token from database
$valid_user = null;
if ($token) {
  $stmt = $pdo->prepare('SELECT id, username, full_name, reset_expiry FROM users WHERE reset_token = ? AND reset_expiry > NOW()');
  $stmt->execute([$token]);
  $valid_user = $stmt->fetch();
}

if (!$token || !$valid_user) {
  $error = 'Invalid or expired reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  
  if ($password === '' || $confirm_password === '') {
    $error = 'Both password fields are required.';
  } elseif ($password !== $confirm_password) {
    $error = 'Passwords do not match.';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters long.';
  } else {
    // Update password and clear reset token
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $user_id = $valid_user['id'];
    
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?');
    $stmt->execute([$hash, $user_id]);
    
    $message = 'Password updated successfully! <a href="' . $base . '/login.php">Click here to login</a>';
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
          <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="96" height="96" class="logo-tilt"/>
        </div>
        <h4 class="mb-3 text-center">Reset Password</h4>
        
        <?php if ($message): ?>
          <div class="alert alert-success py-2"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!$message): ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="password" required autocomplete="new-password" />
              <div class="form-text">Must be at least 6 characters long.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" name="confirm_password" required autocomplete="new-password" />
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <a href="<?= $base ?>/login.php">Back to login</a>
              <button class="btn btn-primary">Update Password</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>