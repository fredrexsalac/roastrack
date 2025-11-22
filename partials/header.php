<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
// Load cart helpers when available for navbar badge
$cartHelpers = __DIR__ . '/../helpers/cart_helpers.php';
if (file_exists($cartHelpers)) {
  require_once $cartHelpers;
}
// Base path for links when hosted under a subfolder like /delivery
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
// If current script is inside /admin, lift base one level up so assets resolve from /public
if (basename($base) === 'admin') {
  $base = rtrim(dirname($base), '/\\');
}
$baseRoot = $base;
if ($baseRoot === '' || $baseRoot === '/' || $baseRoot === '\\') {
  $baseRoot = '';
}
$current = basename($_SERVER['SCRIPT_NAME']);
$isQueuePage = ($current === 'queue.php');
$isAuthPage = ($current === 'login.php' || $current === 'register.php');
$isAdminArea = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$currentRole = $_SESSION['user']['role'] ?? null;
if ($baseRoot !== $base) {
  $base = $baseRoot;
}
$cartCountNav = 0;
$orderCountNav = 0;
$isStaffUser = isset($_SESSION['user']) && in_array(($currentRole ?? ''), ['admin','staff'], true);
$shouldInjectCustomerAlerts = isset($_SESSION['user']) && !$isStaffUser && !$isAdminArea && !$isQueuePage;
if ($shouldInjectCustomerAlerts) {
  $rrCustomerAlertConfig = [
    'sound' => ($base ?: '/') . '/assets/audio/Alarm%20Beeping%20Sound%20Effect.mp3',
    'endpoint' => ($base ?: '/') . '/reservation_alerts.php',
    'icon' => ($base ?: '/') . '/images/roastrack.png',
    'statusBadges' => [
      'PENDING' => 'secondary',
      'CONFIRMED' => 'primary',
      'IN_PROCESS' => 'warning',
      'READY' => 'success',
      'COMPLETED' => 'success',
      'CANCELLED' => 'danger'
    ],
    'statusMessages' => [
      'PENDING' => 'Waiting for staff to confirm your slot.',
      'CONFIRMED' => 'Crew confirmed your order—prep will start soon.',
      'IN_PROCESS' => 'Items are currently being prepared.',
      'READY' => 'Packed and ready for pickup.',
      'COMPLETED' => 'Picked up and closed. Thank you!',
      'CANCELLED' => 'Reservation was cancelled.'
    ]
  ];
}
if (!$isAdminArea && !$isQueuePage && function_exists('rr_cart_count') && !$isStaffUser) {
  $cartCountNav = rr_cart_count();
}
if (!$isAdminArea && !$isQueuePage && !$isStaffUser && isset($_SESSION['user']['id'])) {
  try {
    $pdoNav = db();
    $stmtNav = $pdoNav->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status IN ('PENDING','CONFIRMED','IN_PROCESS','READY')");
    $stmtNav->execute([$_SESSION['user']['id']]);
    $orderCountNav = (int)$stmtNav->fetchColumn();
  } catch (Throwable $e) {
    $orderCountNav = 0;
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>RoastRack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="icon" type="image/png" href="<?= $base ?>/images/roastrack.png" />
    <link rel="apple-touch-icon" href="<?= $base ?>/images/roastrack.png" />
    <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/assets/css/app.css" />
  </head>
  <body class="<?= trim(($current === 'login.php' ? 'login-page ' : '') . ($isAdminArea ? 'admin-page' : '')) ?>">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-rr">
      <div class="container">
        <a href="<?= $base ?>/<?= $isAdminArea ? 'admin/' : '' ?>" class="navbar-brand d-flex align-items-center gap-2">
          <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="28" height="28" class="brand-logo-tilt"/>
          <?php
            $brandSuffix = '';
            if ($isAdminArea && $currentRole) {
              if ($currentRole === 'admin') {
                $brandSuffix = ' - Admin';
              } elseif ($currentRole === 'staff') {
                $brandSuffix = ' - Staff';
              }
            }
          ?>
          <span class="brand-title">RoastRack<?= htmlspecialchars($brandSuffix) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavMain" aria-controls="navbarNavMain" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavMain">
          <ul class="navbar-nav ms-auto align-items-center gap-2">
            <?php if (!$isAdminArea && !$isQueuePage): ?>
              <li class="nav-item"><a class="nav-link" href="<?= $base ?>/">Home</a></li>
              <li class="nav-item"><a class="nav-link" href="<?= $base ?>/catalog.php">Catalog</a></li>
              <?php if (isset($_SESSION['user']) && !$isStaffUser): ?>
                <li class="nav-item">
                  <a class="btn btn-outline-light btn-sm position-relative" href="<?= $base ?>/catalog.php#orderSummary">
                    <i class="bi bi-cart3"></i>
                    <?php if ($cartCountNav > 0): ?>
                      <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle"><?= $cartCountNav ?></span>
                    <?php endif; ?>
                  </a>
                </li>
                <li class="nav-item">
                  <a class="btn btn-light btn-sm position-relative" href="<?= $base ?>/my_orders.php">
                    <i class="bi bi-receipt"></i> My Orders
                    <?php if ($orderCountNav > 0): ?>
                      <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle"><?= $orderCountNav ?></span>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endif; ?>
              <?php if (isset($_SESSION['user']) && in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)): ?>
                <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/">Admin</a></li>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['user'])): ?>
              <li class="nav-item"><span class="navbar-text text-white-50 small me-2">Hello, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']); ?></span></li>
              <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= $base ?>/logout.php">Logout</a></li>
            <?php elseif (!$isAuthPage): ?>
              <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $base ?>/login.php">Login</a></li>
              <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= $base ?>/register.php">Sign up</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <?php if ($shouldInjectCustomerAlerts): ?>
        <div id="rrSoundHint" class="alert alert-warning d-none d-flex flex-wrap align-items-center gap-2 mb-3">
          <div><i class="bi bi-bell-fill me-1"></i>Enable pickup alerts so we can notify you instantly when an order is READY.</div>
          <button class="btn btn-sm btn-outline-dark" id="rrEnableSoundBtn" type="button">Enable sound</button>
        </div>
        <audio id="rrAlertAudio" preload="auto">
          <source src="<?= htmlspecialchars($rrCustomerAlertConfig['sound']) ?>" type="audio/mpeg" />
        </audio>
        <script>
          window.RRCustomerAlertConfig = <?= json_encode($rrCustomerAlertConfig, JSON_UNESCAPED_SLASHES) ?>;
          document.addEventListener('DOMContentLoaded', function(){
            (function(){
              const cfg = window.RRCustomerAlertConfig;
              if (!cfg) return;
              const hint = document.getElementById('rrSoundHint');
              const btn = document.getElementById('rrEnableSoundBtn');
              const audio = document.getElementById('rrAlertAudio');
              let audioPrimed = false;
              const statusMap = new Map();

              async function primeAudio(){
                if (!audio || audioPrimed) return;
                try {
                  await audio.play();
                  audio.pause();
                  audio.currentTime = 0;
                  audioPrimed = true;
                  hideHint();
                } catch (err) {
                  // waiting for explicit user interaction
                }
              }

              async function requestDevicePermissions(){
                if ('Notification' in window && Notification.permission === 'default') {
                  try { await Notification.requestPermission(); } catch (err) { /* ignore */ }
                }
                if (navigator && navigator.vibrate) {
                  try { navigator.vibrate(1); } catch (err) { /* ignore */ }
                }
              }

              function hideHint(){ if (hint) hint.classList.add('d-none'); }
              function showHint(){ if (hint && !audioPrimed) hint.classList.remove('d-none'); }

              function handleFirstInteraction(){
                primeAudio();
                requestDevicePermissions();
                document.removeEventListener('pointerdown', handleFirstInteraction);
              }

              document.addEventListener('pointerdown', handleFirstInteraction);
              if (btn) btn.addEventListener('click', function(){ primeAudio(); requestDevicePermissions(); });

              function showBanner(message){
                let holder = document.getElementById('rr-global-alerts');
                if (!holder){
                  holder = document.createElement('div');
                  holder.id = 'rr-global-alerts';
                  holder.style.position = 'fixed';
                  holder.style.top = '1rem';
                  holder.style.left = '50%';
                  holder.style.transform = 'translateX(-50%)';
                  holder.style.zIndex = '1080';
                  holder.style.maxWidth = '90%';
                  document.body.appendChild(holder);
                }
                const alert = document.createElement('div');
                alert.className = 'alert alert-success shadow';
                alert.textContent = message;
                holder.appendChild(alert);
                setTimeout(function(){ alert.remove(); }, 6000);
              }

              function playAlert(id, status){
                if (!audioPrimed) {
                  primeAudio();
                  if (!audioPrimed) return;
                }
                const attempt = audio.play();
                if (attempt && typeof attempt.then === 'function') {
                  attempt.catch(function(){});
                }
                if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
                showBanner('Reservation #' + id + ' is ' + status + '. Please pick it up soon.');
                pushNotification(id, status);
              }

              function pushNotification(id, status){
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                try {
                  new Notification('RoastRack Order Update', {
                    body: 'Reservation #' + id + ' is ' + status + '.',
                    icon: cfg.icon || undefined
                  });
                } catch (err) {
                  // ignore notification failures
                }
              }

              function updateStatus(id, status){
                const prev = statusMap.get(id);
                if (prev === status) return;
                statusMap.set(id, status);
                if (status === 'READY' || status === 'COMPLETED') {
                  playAlert(id, status);
                }
              }

              async function poll(){
                try {
                  const resp = await fetch(cfg.endpoint, { cache: 'no-store' });
                  if (!resp.ok) return;
                  const data = await resp.json();
                  const list = data && Array.isArray(data.reservations) ? data.reservations : [];
                  if (list.length) showHint(); else hideHint();
                  list.forEach(function(item){ updateStatus(item.id, item.status); });
                } catch (err) {
                  // ignore network errors
                }
              }

              poll();
              setInterval(poll, 10000);
            })();
          });
        </script>
      <?php endif; ?>
