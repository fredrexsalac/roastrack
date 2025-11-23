<?php
session_start();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // points to site root
if (!isset($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)){
  header('Location: ' . ($base ?: '/') . '/admin/login.php');
  exit;
}

require_once __DIR__ . '/../db.php';
$pdo = db();

// Simple stats
$stats = [
  'reservations_today' => 0,
  'pending' => 0,
  'ready' => 0,
  'income_total' => 0.00,
  'income_today' => 0.00,
];

$today = (new DateTime('today'))->format('Y-m-d');
$stmt = $pdo->prepare("SELECT 
  SUM(CASE WHEN DATE(pickup_at)=? THEN 1 ELSE 0 END) AS reservations_today,
  SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) AS pending,
  SUM(CASE WHEN status='READY' THEN 1 ELSE 0 END) AS ready
FROM reservations");
$stmt->execute([$today]);
$row = $stmt->fetch();
if ($row){
  $stats['reservations_today'] = (int)$row['reservations_today'];
  $stats['pending'] = (int)$row['pending'];
  $stats['ready'] = (int)$row['ready'];
}

// Income totals
$incTotal = $pdo->query("SELECT COALESCE(SUM(ri.qty*ri.unit_price),0) FROM reservations r JOIN reservation_items ri ON ri.reservation_id=r.id WHERE r.status='COMPLETED'")->fetchColumn();
$incTodayStmt = $pdo->prepare("SELECT COALESCE(SUM(ri.qty*ri.unit_price),0) FROM reservations r JOIN reservation_items ri ON ri.reservation_id=r.id WHERE DATE(r.pickup_at)=? AND r.status <> 'CANCELLED'");
$incTodayStmt->execute([$today]);
$incToday = $incTodayStmt->fetchColumn();
$stats['income_total'] = (float)$incTotal;
$stats['income_today'] = (float)$incToday;

// Last 7 days income
$labels = [];
$series = [];
for ($i=6; $i>=0; $i--) {
  $d = (new DateTime($today))->modify("-$i day")->format('Y-m-d');
  $labels[] = $d;
  $series[$d] = 0.0;
}
$rangeStmt = $pdo->prepare("SELECT DATE(r.pickup_at) d, COALESCE(SUM(ri.qty*ri.unit_price),0) t FROM reservations r JOIN reservation_items ri ON ri.reservation_id=r.id WHERE r.status <> 'CANCELLED' AND r.pickup_at >= DATE_SUB(?, INTERVAL 6 DAY) AND DATE(r.pickup_at) <= ? GROUP BY d ORDER BY d");
$rangeStmt->execute([$today, $today]);
while ($r = $rangeStmt->fetch()){
  $series[$r['d']] = (float)$r['t'];
}
$chartLabels = array_values($labels);
$chartData = array_values($series);

include __DIR__ . '/../partials/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="d-flex align-items-center gap-2 mb-3">
      <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="28" height="28" class="brand-logo-tilt"/>
      <strong>RoastRack</strong>
    </div>
    <nav class="nav flex-column">
      <a class="nav-link" href="<?= ($base ?: '/') ?>/"><i class="bi bi-house me-1"></i> Home</a>
      <a class="nav-link" href="inventory.php"><i class="bi bi-box-seam me-1"></i> Inventory</a>
      <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)): ?>
      <a class="nav-link" href="<?= ($base ?: '/') ?>/queue.php"><i class="bi bi-receipt me-1"></i> Orders</a>
      <?php endif; ?>
      <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
      <a class="nav-link" href="customers.php"><i class="bi bi-people me-1"></i> Customers</a>
      <?php endif; ?>
      <a class="nav-link" href="notifications.php"><i class="bi bi-bell me-1"></i> Notifications</a>
    </nav>
  </aside>
  <section class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Admin Dashboard</h4>
      <div>
        <a class="btn btn-sm btn-outline-light" href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?: '/' ?>/">Home</a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Reservations Today</div>
            <div class="display-6"><?= (int)$stats['reservations_today'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Pending</div>
            <div class="display-6"><?= (int)$stats['pending'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Ready</div>
            <div class="display-6"><?= (int)$stats['ready'] ?></div>
          </div>
        </div>
      </div>
      <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
      <div class="col-md-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Estimated Income Today</div>
            <div class="display-6">₱<?= number_format($stats['income_today'], 2) ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Reservation Queue</h5>
            <p class="text-muted">Manage pickup schedule and statuses.</p>
            <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)): ?>
              <a class="btn btn-primary" href="<?= ($base ?: '/') ?>/queue.php">Open Queue</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Inventory</h5>
            <p class="text-muted">Add/update menu items and prices.</p>
            <a class="btn btn-outline-primary" href="inventory.php">Open Inventory</a>
          </div>
        </div>
      </div>
      <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
      <div class="col-lg-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Total Income</h5>
            <div class="display-6 mb-2">₱<?= number_format($stats['income_total'], 2) ?></div>
            <div class="text-muted small">All-time from completed reservations</div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="col-lg-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Staff Tools</h5>
            <p class="text-muted">Need admin-level metrics? Ask an admin for access.</p>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Revenue (Last 7 Days)</h5>
            <canvas id="rev7"></canvas>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  (function(){
    const ctx = document.getElementById('rev7');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_map(fn($d)=>date('M d', strtotime($d)), $chartLabels)) ?>,
        datasets: [{
          label: 'Income (₱)',
          data: <?= json_encode($chartData) ?>,
          backgroundColor: 'rgba(13,110,253,0.5)',
          borderColor: 'rgba(13,110,253,1)',
          borderWidth: 1
        }]
      },
      options: {
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
      }
    });
  })();
</script>
