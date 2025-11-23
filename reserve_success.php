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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: ' . $base . '/login.php');
  exit;
}

$stmt = $pdo->prepare('SELECT r.id, r.pickup_at, r.status, COALESCE(u.username, r.customer_name) AS customer, r.customer_phone,
  r.payment_method, r.gcash_account_label, r.gcash_account_number
  FROM reservations r
  LEFT JOIN users u ON u.id = r.user_id
  WHERE r.id = ?');
$stmt->execute([$id]);
$res = $stmt->fetch();
if (!$res) {
  header('Location: ' . $base . '/login.php');
  exit;
}

$items = $pdo->prepare('SELECT item_name, qty FROM reservation_items WHERE reservation_id = ?');
$items->execute([$id]);
$list = $items->fetchAll();

$beepAsset = $base . '/assets/audio/Alarm%20Beeping%20Sound%20Effect.mp3';

include __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Reservation placed!</h4>
        <p>Your pickup time: <strong><?= htmlspecialchars((new DateTime($res['pickup_at']))->format('M d, Y h:ia')) ?></strong></p>
        <p>Status: <span class="badge text-bg-secondary"><?= htmlspecialchars($res['status']) ?></span></p>
        <p>Name: <?= htmlspecialchars($res['customer'] ?? '') ?><?php if(!empty($res['customer_phone'])):?> (<?= htmlspecialchars($res['customer_phone']) ?>)<?php endif; ?></p>
        <p>Payment: <strong><?= $res['payment_method'] === 'GCASH' ? 'GCash (proof under review)' : 'Pay at pickup' ?></strong></p>
        <?php if ($res['payment_method'] === 'GCASH'): ?>
          <div class="border rounded p-3 mb-3 bg-light-subtle">
            <div class="small text-uppercase text-muted">You selected</div>
            <div class="fw-semibold"><?= htmlspecialchars($res['gcash_account_label'] ?: 'GCash') ?></div>
            <?php if (!empty($res['gcash_account_number'])): ?>
              <div class="text-muted">GCash: <?= htmlspecialchars($res['gcash_account_number']) ?></div>
            <?php endif; ?>
            <small class="text-muted d-block mt-2">Please wait for admins to verify your upload before heading out.</small>
          </div>
        <?php endif; ?>
        <hr/>
        <h6>Items</h6>
        <ul class="mb-3">
          <?php foreach ($list as $row): ?>
            <li><?= htmlspecialchars($row['item_name']) ?> × <?= (int)$row['qty'] ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn-primary" href="<?= $base ?>/catalog.php">Back to Catalog</a>
      </div>
    </div>
  </div>
</div>
<audio id="readySound" preload="auto">
  <source src="<?= htmlspecialchars($beepAsset) ?>" type="audio/mpeg">
  <!-- Local beeping effect stored under assets/audio -->
</audio>
<script>
  const id = <?= (int)$res['id'] ?>;
  const badge = document.querySelector('.badge');
  const sound = document.getElementById('readySound');
  let notified = false;
  async function poll(){
    try {
      const r = await fetch('<?= $base ?>/reservation_status.php?id=' + id);
      const j = await r.json();
      if (j && j.status) {
        badge.textContent = j.status;
        if (!notified && (j.status === 'READY' || j.status === 'COMPLETED')){
          notified = true;
          try { sound.play(); } catch(e){}
          alert('Your order is ' + j.status + '. Please proceed to pickup.');
        }
      }
    } catch (e) { /* ignore */ }
  }
  setInterval(poll, 10000);
  poll();
  document.addEventListener('visibilitychange', ()=>{ if (document.visibilityState === 'visible') poll(); });
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
