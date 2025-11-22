<?php
session_start();
require_once __DIR__ . '/db.php';
$pdo = db();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Allow admin or staff, require auth
if (!isset($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)){
  header('Location: ' . ($base ?: '/') . '/admin/login.php');
  exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['res_id'], $_POST['action'])){
  $resId = (int)$_POST['res_id'];
  $action = $_POST['action'];
  $allowed = ['CONFIRMED','IN_PROCESS','READY','COMPLETED','CANCELLED'];
  if ($resId > 0 && in_array($action, $allowed, true)){
    $upd = $pdo->prepare('UPDATE reservations SET status=? WHERE id=?');
    $upd->execute([$action, $resId]);
  }
  header('Location: ' . ($base ?: '/') . '/queue.php');
  exit;
}

$statusOptions = ['PENDING','CONFIRMED','IN_PROCESS','READY','COMPLETED','CANCELLED'];
$status = $_GET['status'] ?? '';
$where = '';
$params = [];
if (in_array($status, $statusOptions, true)){
  $where = 'WHERE r.status = ?';
  $params[] = $status;
}

$sql = "SELECT r.id, r.pickup_at, r.status, COALESCE(u.username, r.customer_name) AS customer, r.customer_phone,
               r.payment_method, r.gcash_account_label, r.gcash_account_number
        FROM reservations r
        LEFT JOIN users u ON u.id = r.user_id
        $where
        ORDER BY r.pickup_at ASC, r.id ASC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Fetch items per reservation
$itemStmt = $pdo->prepare('SELECT item_name, qty FROM reservation_items WHERE reservation_id = ?');

include __DIR__ . '/partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Reservation Queue</h4>
  <form class="d-flex align-items-center gap-2" method="get">
    <select class="form-select form-select-sm" name="status">
      <option value="">All statuses</option>
      <?php foreach ($statusOptions as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-light">Filter</button>
  </form>
</div>

<div class="mb-3">
  <a class="btn btn-sm btn-outline-secondary rr-back-btn" href="<?= ($base ?: '/') ?>/admin/">&larr; Back to Dashboard</a>
  </div>

<?php if (!$reservations): ?>
  <div class="alert alert-info">No reservations found.</div>
<?php endif; ?>

<div class="list-group">
<?php foreach ($reservations as $r): ?>
  <?php $itemStmt->execute([$r['id']]); $items = $itemStmt->fetchAll(); ?>
  <div class="list-group-item">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="fw-bold">#<?= (int)$r['id'] ?> • <?= htmlspecialchars((new DateTime($r['pickup_at']))->format('M d, Y h:ia')) ?></div>
        <div class="text-muted small">Customer: <?= htmlspecialchars($r['customer'] ?? '') ?><?php if(!empty($r['customer_phone'])):?> (<?= htmlspecialchars($r['customer_phone']) ?>)<?php endif; ?></div>
        <ul class="small mb-2">
          <?php foreach ($items as $it): ?>
            <li><?= htmlspecialchars($it['item_name']) ?> × <?= (int)$it['qty'] ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="text-muted small">Payment: <?= htmlspecialchars($r['payment_method'] === 'GCASH' ? 'GCash (with proof)' : 'Pay at pickup') ?></div>
        <?php if ($r['payment_method'] === 'GCASH'): ?>
          <div class="text-muted small">
            <?php if (!empty($r['gcash_account_label']) || !empty($r['gcash_account_number'])): ?>
              Account: <?= htmlspecialchars($r['gcash_account_label'] ?: 'GCash') ?><?= $r['gcash_account_number'] ? ' — ' . htmlspecialchars($r['gcash_account_number']) : '' ?>
            <?php else: ?>
              Account: (Not recorded)
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="text-end">
        <?php
          $icon = 'bi-hourglass';
          if ($r['status']==='CONFIRMED') $icon='bi-clipboard-check';
          elseif ($r['status']==='IN_PROCESS') $icon='bi-fire';
          elseif ($r['status']==='READY') $icon='bi-check2-circle';
          elseif ($r['status']==='COMPLETED') $icon='bi-check-all';
          elseif ($r['status']==='CANCELLED') $icon='bi-x-circle';
        ?>
        <span class="badge text-bg-secondary"><i class="bi <?= $icon ?> me-1"></i><?= htmlspecialchars($r['status']) ?></span>
        <form method="post" class="mt-2 d-flex flex-wrap gap-1 justify-content-end">
          <input type="hidden" name="res_id" value="<?= (int)$r['id'] ?>" />
          <?php if ($r['status']==='PENDING'): ?>
            <button class="btn btn-sm btn-outline-primary" name="action" value="CONFIRMED" type="submit">Confirm</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="CANCELLED" type="submit">Cancel</button>
          <?php elseif ($r['status']==='CONFIRMED'): ?>
            <button class="btn btn-sm btn-outline-secondary" name="action" value="IN_PROCESS" type="submit"><i class="bi bi-fire me-1"></i>In Process</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="CANCELLED" type="submit">Cancel</button>
          <?php elseif ($r['status']==='IN_PROCESS'): ?>
            <button class="btn btn-sm btn-outline-warning" name="action" value="READY" type="submit">Ready</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="CANCELLED" type="submit">Cancel</button>
          <?php elseif ($r['status']==='READY'): ?>
            <button class="btn btn-sm btn-outline-success" name="action" value="COMPLETED" type="submit">Complete</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="CANCELLED" type="submit">Cancel</button>
          <?php endif; ?>
        </form>
        <form method="post" class="mt-2 d-flex align-items-center gap-1">
          <input type="hidden" name="res_id" value="<?= (int)$r['id'] ?>" />
          <select class="form-select form-select-sm" name="action">
            <option value="" selected disabled>Set status…</option>
            <?php foreach ($statusOptions as $option): ?>
              <option value="<?= $option ?>" <?= $option === $r['status'] ? 'disabled' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary" type="submit">Update</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
