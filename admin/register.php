<?php
session_start();
require_once __DIR__ . '/../db.php';
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

$modes = ['admin','staff'];
$role = $_POST['role'] ?? ($_GET['mode'] ?? 'staff');
if (!in_array($role, $modes, true)) { $role = 'staff'; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $full_name = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role = $_POST['role'] ?? $role;
  if (!in_array($role, $modes, true)) { $role = 'staff'; }

  error_log("Admin register POST: username='$username', full_name='$full_name', phone='$phone', role='$role'");

  if ($username === '' || $password === '' || $full_name === '' || $phone === ''){
    $error = 'Full name, phone, username, and password are required.';
    error_log("Validation error: $error");
  } else {
    try {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
      $stmt->execute([$username]);
      if ($stmt->fetch()){
        $error = 'Username already exists.';
        error_log("Username already exists: $username");
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        error_log("Creating user: username='$username', role='$role', hash='$hash'");
        $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, phone, email) VALUES (?,?,?,?,?,?)');
        $ins->execute([$username, $hash, $role, $full_name, $phone, $email ?: null]);
        $newId = $pdo->lastInsertId();
        error_log("User created successfully with ID: $newId");
        $_SESSION['user'] = [ 'id'=>$newId, 'username'=>$username, 'role'=>$role, 'full_name'=>$full_name, 'phone'=>$phone ];
        error_log("Session set, redirecting to /admin/");
        header('Location: ' . $base . '/admin/');
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Registration failed: ' . $e->getMessage();
      error_log("Registration exception: " . $e->getMessage());
    }
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
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm bbq-card">
      <div class="card-body p-4">
        <div class="text-center mb-2">
          <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="96" height="96" class="logo-tilt"/>
        </div>
        <div class="text-center mb-2"><span class="bbq-badge"><i class="bi bi-person-gear me-1"></i> Create Admin/Staff</span></div>
        <div class="d-flex justify-content-center gap-2 mb-3" id="registerModeSwitch">
          <button type="button" class="btn btn-sm <?= $role==='admin' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mode="admin"><i class="bi bi-person-gear me-1"></i> Admin</button>
          <button type="button" class="btn btn-sm <?= $role==='staff' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mode="staff"><i class="bi bi-person-badge me-1"></i> Staff</button>
        </div>
        <h3 class="mb-1 text-center bbq-title" id="registerHeading">Register <?= $role === 'admin' ? 'Admin' : 'Staff' ?></h3>
        <p class="text-center text-muted mb-4" id="registerSubtext"><?= $role === 'admin' ? 'Create a new administrator with full access.' : 'Create a staff account for queue, inventory, and notifications.' ?></p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="role" id="register-role" value="<?= htmlspecialchars($role) ?>" />
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
            <a href="<?= $base ?>/admin/login.php">Have an account? Login</a>
            <button class="btn btn-primary">Create account</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
  const regButtons = document.querySelectorAll('#registerModeSwitch [data-mode]');
  const regInput = document.getElementById('register-role');
  const regHeading = document.getElementById('registerHeading');
  const regSub = document.getElementById('registerSubtext');
  const regCopy = {
    admin: {
      title: 'Register Admin',
      text: 'Create a new administrator with full access.'
    },
    staff: {
      title: 'Register Staff',
      text: 'Create a staff account for queue, inventory, and notifications.'
    }
  };
  regButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.mode;
      regInput.value = mode;
      regButtons.forEach(b => {
        b.classList.toggle('btn-primary', b === btn);
        b.classList.toggle('btn-outline-primary', b !== btn);
      });
      regHeading.textContent = regCopy[mode].title;
      regSub.textContent = regCopy[mode].text;
    });
  });
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
