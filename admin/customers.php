<?php
session_start();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
if (!isset($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)){
  header('Location: ' . ($base ?: '/') . '/admin/login.php');
  exit;
}
require_once __DIR__ . '/../db.php';
$pdo = db();

$isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin){
  try {
    $uid = (int)($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? 'customer';
    if (!in_array($role, ['admin','staff','customer'], true)) { $role = 'customer'; }
    if ($uid > 0){
      $stmt = $pdo->prepare('UPDATE users SET role=? WHERE id=?');
      $stmt->execute([$role, $uid]);
      $msg = 'Role updated.';
    }
  } catch (Throwable $e) { $err = 'Failed to update user.'; }
}

$q = trim($_GET['q'] ?? '');
$params = ['customer'];
$where = ['role = ?'];
if ($q !== ''){
  $where[] = '(username LIKE ? OR full_name LIKE ?)';
  $params[] = '%'.$q.'%';
  $params[] = '%'.$q.'%';
}
$sql = 'SELECT id, username, full_name, phone, email, role FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="d-flex align-items-center gap-2 mb-3">
      <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="28" height="28" class="brand-logo-tilt"/>
      <strong>RoastRack</strong>
    </div>
    <nav class="nav flex-column">
      <a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i> Home</a>
      <a class="nav-link" href="inventory.php"><i class="bi bi-box-seam me-1"></i> Inventory</a>
      <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)): ?>
      <a class="nav-link" href="<?= ($base ?: '/') ?>/queue.php"><i class="bi bi-receipt me-1"></i> Orders</a>
      <?php endif; ?>
      <a class="nav-link active" href="customers.php"><i class="bi bi-people me-1"></i> Customers</a>
      <a class="nav-link" href="staff.php"><i class="bi bi-person-badge me-1"></i> Staff</a>
      <a class="nav-link" href="notifications.php"><i class="bi bi-bell me-1"></i> Notifications</a>
    </nav>
  </aside>
  <section class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Customers</h4>
      <form class="d-flex align-items-center gap-2" method="get">
        <input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name or username..."/>
        <button class="btn btn-sm btn-outline-secondary">Search</button>
      </form>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>Username</th>
                <th>Full name</th>
                <th>Phone</th>
                <th>Email</th>
                <th style="width:200px">Role</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                <td class="small text-muted"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td>
                  <?php if ($isAdmin): ?>
                  <form class="d-flex gap-2" method="post">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                    <select class="form-select form-select-sm" name="role">
                      <option value="customer" <?= ($u['role']==='customer'?'selected':'') ?>>Customer</option>
                      <option value="staff" <?= ($u['role']==='staff'?'selected':'') ?>>Staff</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary">Save</button>
                  </form>
                  <?php else: ?>
                    <span class="badge text-bg-secondary"><?= htmlspecialchars($u['role']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$users): ?>
              <tr><td colspan="6" class="text-center text-muted">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
