<?php
session_start();
require_once __DIR__ . '/../db.php';
$pdo = db();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // points to site root

$modes = ['admin','staff'];
$mode = $_POST['mode'] ?? 'admin';
if (!in_array($mode, $modes, true)) {
  $mode = 'admin';
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  try {
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, full_name, phone FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    $expectedRole = ($mode === 'admin') ? 'admin' : 'staff';
    if ($user && password_verify($password, $user['password_hash']) && ($user['role'] ?? '') === $expectedRole){
      $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'] ?? null,
        'phone' => $user['phone'] ?? null,
        'role' => $user['role']
      ];
      header('Location: ' . ($base ?: '/') . '/admin/');
      exit;
    } else {
      $error = 'Invalid ' . ($mode === 'admin' ? 'admin' : 'staff') . ' credentials.';
    }
  } catch (Throwable $e) {
    $error = 'Login failed.';
  }
}

include __DIR__ . '/../partials/header.php';
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
          <img src="<?= ($base ?: '/') ?>/images/roastrack.png" alt="RoastRack" width="96" height="96" class="logo-tilt"/>
        </div>
        <div class="text-center mb-2"><span class="bbq-badge"><i class="bi bi-shield-lock me-1"></i> Admin Access</span></div>
        <div class="d-flex justify-content-center gap-2 mb-3" id="loginModeSwitch">
          <button type="button" class="btn btn-sm <?= $mode==='admin' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mode="admin"><i class="bi bi-person-gear me-1"></i> Admin</button>
          <button type="button" class="btn btn-sm <?= $mode==='staff' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mode="staff"><i class="bi bi-person-badge me-1"></i> Staff</button>
        </div>
        <h3 class="mb-1 text-center bbq-title" id="loginHeading"><?= $mode === 'admin' ? 'Admin Login' : 'Staff Login' ?></h3>
        <p class="text-center text-muted mb-4" id="loginSubtext"><?= $mode === 'admin' ? 'Sign in with an administrator account to manage everything.' : 'Sign in with a staff account to access queue, inventory, and notifications.' ?></p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="mode" id="login-mode" value="<?= htmlspecialchars($mode) ?>" />
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
          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= ($base ?: '/') ?>/admin/register.php">Register admin/staff</a>
            <button class="btn btn-primary">Login</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
  const modeButtons = document.querySelectorAll('#loginModeSwitch [data-mode]');
  const modeInput = document.getElementById('login-mode');
  const heading = document.getElementById('loginHeading');
  const subtext = document.getElementById('loginSubtext');
  const copy = {
    admin: {
      title: 'Admin Login',
      text: 'Sign in with an administrator account to manage everything.',
      badge: 'Admin Access'
    },
    staff: {
      title: 'Staff Login',
      text: 'Sign in with a staff account to access queue, inventory, and notifications.',
      badge: 'Staff Access'
    }
  };
  modeButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.mode;
      modeInput.value = mode;
      modeButtons.forEach(b => {
        b.classList.toggle('btn-primary', b === btn);
        b.classList.toggle('btn-outline-primary', b !== btn);
      });
      heading.textContent = copy[mode].title;
      subtext.textContent = copy[mode].text;
    });
  });
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
