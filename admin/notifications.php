<?php
session_start();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
if (!isset($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)){
  header('Location: ' . ($base ?: '/') . '/admin/login.php');
  exit;
}
require_once __DIR__ . '/../db.php';
$pdo = db();

// Recent READY/COMPLETED orders (last 50)
$stmt = $pdo->query("SELECT id, status, pickup_at FROM reservations WHERE status IN ('READY','COMPLETED') ORDER BY id DESC LIMIT 50");
$list = $stmt->fetchAll();

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
      <a class="nav-link" href="<?= ($base ?: '/') ?>/queue.php"><i class="bi bi-receipt me-1"></i> Orders</a>
      <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
      <a class="nav-link" href="customers.php"><i class="bi bi-people me-1"></i> Customers</a>
      <a class="nav-link" href="staff.php"><i class="bi bi-person-badge me-1"></i> Staff</a>
      <?php endif; ?>
      <a class="nav-link active" href="notifications.php"><i class="bi bi-bell me-1"></i> Notifications</a>
    </nav>
  </aside>
  <section class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Notifications</h4>
    </div>

    <div class="card">
      <div class="card-body">
        <p class="text-muted">Recent READY/COMPLETED orders. Use Queue page to update statuses; triggering an alert can be integrated with a PWA or client sound.</p>
        <ul class="list-group">
          <?php foreach ($list as $row): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>#<?= (int)$row['id'] ?> — <?= htmlspecialchars($row['status']) ?></span>
              <span class="text-muted small"><?= htmlspecialchars((new DateTime($row['pickup_at']))->format('M d, Y h:ia')) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (!$list): ?>
            <li class="list-group-item text-muted">No notifications yet.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
