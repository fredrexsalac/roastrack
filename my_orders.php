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

if (!isset($_SESSION['user'])) {
  header('Location: ' . $base . '/login.php?msg=' . urlencode('Please log in to view your orders.'));
  exit;
}
if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)) {
  header('Location: ' . $base . '/admin/');
  exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$activeStatuses = ['PENDING','CONFIRMED','IN_PROCESS','READY'];
$statusBadges = [
  'PENDING' => 'secondary',
  'CONFIRMED' => 'primary',
  'IN_PROCESS' => 'warning',
  'READY' => 'success',
  'COMPLETED' => 'success',
  'CANCELLED' => 'danger'
];
$statusMessages = [
  'PENDING' => 'Waiting for staff to confirm your slot.',
  'CONFIRMED' => 'Crew confirmed your order—prep will start soon.',
  'IN_PROCESS' => 'Items are currently being prepared.',
  'READY' => 'Packed and ready for pickup.',
  'COMPLETED' => 'Picked up and closed. Thank you!',
  'CANCELLED' => 'Reservation was cancelled.'
];

$stmt = $pdo->prepare('SELECT id, status, pickup_at, created_at, payment_method, gcash_account_label, gcash_account_number FROM reservations WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

// Preload items per reservation
$itemStmt = $pdo->prepare('SELECT item_name, qty FROM reservation_items WHERE reservation_id = ?');
$resItems = [];
foreach ($reservations as $row) {
  $itemStmt->execute([$row['id']]);
  $resItems[$row['id']] = $itemStmt->fetchAll();
}

$watchReservations = array_values(array_filter($reservations, function($row) {
  return in_array($row['status'], ['PENDING','CONFIRMED','IN_PROCESS'], true);
}));
$pollPayload = array_map(function($row) {
  return ['id' => (int)$row['id'], 'status' => $row['status']];
}, $watchReservations);
$readySoundSrc = $base . '/assets/audio/Alarm%20Beeping%20Sound%20Effect.mp3';

include __DIR__ . '/partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1">My Orders</h4>
    <p class="text-muted mb-0">Track your pickup reservations and their current status.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?= $base ?>/catalog.php"><i class="bi bi-plus-circle"></i> New order</a>
</div>

<?php if (!$reservations): ?>
  <div class="alert alert-info">You have no reservations yet. Visit the catalog to start a new order.</div>
<?php else: ?>
  <div class="list-group">
    <?php foreach ($reservations as $row): ?>
      <?php
        $status = $row['status'];
        $badge = $statusBadges[$status] ?? 'secondary';
        $items = $resItems[$row['id']] ?? [];
        $messageClass = $status === 'READY' ? 'text-success' : 'text-muted';
      ?>
      <div class="list-group-item py-3" data-res-card="<?= (int)$row['id'] ?>">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div>
            <div class="fw-semibold">Reservation #<?= (int)$row['id'] ?></div>
            <div class="text-muted small">Pickup: <?= htmlspecialchars((new DateTime($row['pickup_at']))->format('M d, Y h:ia')) ?></div>
            <div class="mt-2 small text-muted">Placed: <?= htmlspecialchars((new DateTime($row['created_at']))->format('M d, Y h:ia')) ?></div>
          </div>
          <div class="text-end">
            <div class="small text-uppercase text-muted">Status</div>
            <span class="badge text-bg-<?= $badge ?>" data-res-badge="<?= (int)$row['id'] ?>"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($status) ?></span>
            <?php if (!empty($statusMessages[$status])): ?>
              <div class="small mt-1 <?= $messageClass ?>" data-res-message="<?= (int)$row['id'] ?>">
                <?= htmlspecialchars($statusMessages[$status]) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="mt-3">
          <div class="small text-uppercase text-muted">Items</div>
          <ul class="mb-2 small">
            <?php foreach ($items as $it): ?>
              <li><?= htmlspecialchars($it['item_name']) ?> × <?= (int)$it['qty'] ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-muted">
          <span><i class="bi bi-wallet2 me-1"></i><?= $row['payment_method'] === 'GCASH' ? 'GCash' : 'Pay at pickup' ?></span>
          <?php if ($row['payment_method'] === 'GCASH' && !empty($row['gcash_account_label'])): ?>
            <span><i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($row['gcash_account_label']) ?><?= $row['gcash_account_number'] ? ' — ' . htmlspecialchars($row['gcash_account_number']) : '' ?></span>
          <?php endif; ?>
          <a class="text-decoration-none" href="<?= $base ?>/reserve_success.php?id=<?= (int)$row['id'] ?>">View details</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($pollPayload)): ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" id="soundHint">
  <div><i class="bi bi-bell-fill me-1"></i>Tap once to enable sound alerts so we can notify you instantly when an order is READY.</div>
  <button class="btn btn-sm btn-outline-dark" id="enableSoundBtn" type="button">Enable sound</button>
</div>
<audio id="orderReadyAudio" preload="auto">
  <source src="<?= htmlspecialchars($readySoundSrc) ?>" type="audio/mpeg" />
</audio>
<script>
  (function(){
    const pollTargets = <?= json_encode($pollPayload, JSON_UNESCAPED_SLASHES) ?>;
    if (!pollTargets.length) return;
    const baseUrl = <?= json_encode($base) ?>;
    const statusMessages = <?= json_encode($statusMessages, JSON_UNESCAPED_SLASHES) ?>;
    const statusBadges = <?= json_encode($statusBadges, JSON_UNESCAPED_SLASHES) ?>;
    const audio = document.getElementById('orderReadyAudio');
    let audioPrimed = false;

    async function primeAudio(){
      if (!audio || audioPrimed) return;
      try {
        await audio.play();
        audio.pause();
        audio.currentTime = 0;
        audioPrimed = true;
        const hint = document.getElementById('soundHint');
        if (hint) hint.classList.add('d-none');
      } catch (err) {
        // user interaction still required
      }
    }

    document.addEventListener('pointerdown', primeAudio, { once: true });
    const enableBtn = document.getElementById('enableSoundBtn');
    if (enableBtn){
      enableBtn.addEventListener('click', function(){ primeAudio(); });
    }

    function updateStatusUI(id, status){
      const badge = document.querySelector('[data-res-badge="' + id + '"]');
      if (badge){
        const badgeClass = statusBadges[status] || 'secondary';
        badge.className = 'badge text-bg-' + badgeClass;
        badge.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + status;
      }
      const message = document.querySelector('[data-res-message="' + id + '"]');
      if (message){
        message.textContent = statusMessages[status] || '';
        if (status === 'READY'){
          message.classList.add('text-success');
          message.classList.remove('text-muted');
        } else {
          message.classList.remove('text-success');
          message.classList.add('text-muted');
        }
      }
    }

    function playAlert(){
      if (!audio) return;
      if (!audioPrimed) {
        primeAudio();
        if (!audioPrimed) return;
      }
      const playPromise = audio.play();
      if (playPromise && typeof playPromise.then === 'function'){
        playPromise.catch(function(){});
      }
    }

    function showBanner(message){
      let holder = document.getElementById('ready-alert-holder');
      if (!holder){
        holder = document.createElement('div');
        holder.id = 'ready-alert-holder';
        holder.style.position = 'fixed';
        holder.style.top = '1rem';
        holder.style.left = '50%';
        holder.style.transform = 'translateX(-50%)';
        holder.style.zIndex = '1055';
        holder.style.maxWidth = '90%';
        document.body.appendChild(holder);
      }
      const alert = document.createElement('div');
      alert.className = 'alert alert-success shadow';
      alert.textContent = message;
      holder.appendChild(alert);
      setTimeout(function(){ alert.remove(); }, 6000);
    }

    function highlightCard(id){
      const card = document.querySelector('[data-res-card="' + id + '"]');
      if (!card) return;
      card.classList.add('border', 'border-success', 'shadow-sm');
      setTimeout(function(){
        card.classList.remove('border-success', 'shadow-sm');
      }, 4000);
    }

    async function pollOnce(){
      await Promise.all(pollTargets.map(async function(target){
        if (target.notified) return;
        try {
          const resp = await fetch(baseUrl + '/reservation_status.php?id=' + target.id, { cache: 'no-store' });
          const data = await resp.json();
          if (!data || !data.status) return;
          if (data.status !== target.status){
            target.status = data.status;
            updateStatusUI(target.id, data.status);
          }
          if (!target.notified && (data.status === 'READY' || data.status === 'COMPLETED')){
            target.notified = true;
            playAlert();
            if (navigator.vibrate) navigator.vibrate(200);
            highlightCard(target.id);
            showBanner('Reservation #' + target.id + ' is ' + data.status + '. Please pick it up soon.');
          }
        } catch (err) {
          // ignore network errors
        }
      }));
    }

    pollOnce();
    setInterval(pollOnce, 10000);
  })();
</script>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
