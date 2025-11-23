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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  
  if ($username === '') {
    $error = 'Username is required.';
  } else {
    $stmt = $pdo->prepare('SELECT id, username, full_name FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
      // Generate a simple reset token (in production, use email and more secure method)
      $reset_token = bin2hex(random_bytes(16));
      $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
      
      // Store reset token in session (simplified approach)
      $_SESSION['reset_token'] = $reset_token;
      $_SESSION['reset_user_id'] = $user['id'];
      $_SESSION['reset_expiry'] = $expiry;
      
      $message = "Password reset link created! For demo purposes, the reset token is: <strong>$reset_token</strong><br>
                  In production, this would be sent via email.<br>
                  <a href='$base/reset_password.php?token=$reset_token'>Click here to reset password</a>";
    } else {
      // Don't reveal if user exists or not
      $message = "If the username exists, a reset link has been generated.";
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
          <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="96" height="96" class="logo-tilt"/>
        </div>
        <h4 class="mb-3 text-center">Forgot Password</h4>
        
        <?php if ($message): ?>
          <div class="alert alert-info py-2"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required autocomplete="username" />
            <div class="form-text">Enter your username to receive a password reset link.</div>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= $base ?>/login.php">Back to login</a>
            <button class="btn btn-primary">Send Reset Link</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
