<?php
session_start();
require_once __DIR__ . '/db.php';
$pdo = db();

$user = $_SESSION['user'] ?? null;
$displayName = trim($user['full_name'] ?? $user['username'] ?? 'Friend');
if ($displayName === '') {
  $displayName = 'Friend';
}
$nameParts = preg_split('/\s+/', $displayName);
$firstName = $nameParts[0] ?? $displayName;
$isStaff = $user && in_array(($user['role'] ?? ''), ['admin','staff'], true);
$lastReservation = null;
if ($user && isset($user['id'])) {
  $stmt = $pdo->prepare('SELECT id, pickup_at, status FROM reservations WHERE user_id = ? ORDER BY id DESC LIMIT 1');
  $stmt->execute([$user['id']]);
  $lastReservation = $stmt->fetch();
}

include __DIR__ . '/partials/header.php';
?>
<style>
  .home-hero {
    background: linear-gradient(135deg, #7a3d0c, #e77724);
    color: #fff;
    border-radius: 20px;
    padding: 2.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 1.5rem 3rem rgba(122, 61, 12, 0.35);
  }
  .home-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.2), transparent 55%);
    opacity: .5;
    pointer-events: none;
  }
  .home-hero-auth {
    background: linear-gradient(135deg, #0f172a, #1f6feb);
    box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.45);
  }
  .home-hero-content {
    position: relative;
    z-index: 2;
  }
  .home-hero h1 {
    font-size: clamp(2rem, 4vw, 2.9rem);
  }
  .home-hero p.lead {
    font-size: 1.05rem;
    max-width: 36rem;
  }
  .hero-actions .btn {
    min-width: 170px;
  }
  .hero-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .85rem;
    border-radius: 999px;
    background: rgba(255,255,255,0.15);
    font-size: .85rem;
    text-transform: uppercase;
    letter-spacing: .05em;
  }
  .quick-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 1.5rem 2.5rem rgba(15, 23, 42, 0.08);
  }
  .quick-card .card-title {
    font-weight: 600;
  }
  .discover-card {
    border-radius: 18px;
    background: linear-gradient(135deg, #fff, #f7f2eb);
    border: 1px solid rgba(0,0,0,0.05);
  }
  .discover-card h5 {
    font-weight: 600;
  }
  @media (max-width: 575px) {
    .hero-actions .btn {
      width: 100%;
    }
  }
</style>

<div class="home-hero <?php echo $user ? 'home-hero-auth' : ''; ?> mb-4">
  <div class="home-hero-content">
    <?php if ($user): ?>
      <?php if ($isStaff): ?>
        <span class="hero-chip"><i class="bi bi-gear-wide-connected"></i> Crew tools</span>
        <h1 class="mt-3 mb-2">Hey <?php echo htmlspecialchars($firstName); ?>, ops mode is ready 🔧</h1>
        <p class="lead mb-3">Jump into the admin HQ, keep the queue flowing, and tune inventory levels—all without switching contexts.</p>
        <div class="d-flex flex-wrap gap-2 hero-actions">
          <a class="btn btn-light btn-lg" href="<?= $base ?>/admin/"><i class="bi bi-speedometer2"></i> Admin HQ</a>
          <a class="btn btn-outline-light btn-lg" href="<?= $base ?>/queue.php"><i class="bi bi-receipt"></i> Manage queue</a>
          <a class="btn btn-outline-light btn-lg" href="<?= $base ?>/admin/inventory.php"><i class="bi bi-box"></i> Update inventory</a>
        </div>
      <?php else: ?>
        <span class="hero-chip"><i class="bi bi-stars"></i> Welcome back</span>
        <h1 class="mt-3 mb-2">Hey <?php echo htmlspecialchars($firstName); ?>, the grill is ready for you 🔥</h1>
        <p class="lead mb-3">Many things await—explore new bundles before the rush hits.</p>
        <div class="d-flex flex-wrap gap-2 hero-actions">
          <a class="btn btn-light btn-lg" href="<?= $base ?>/catalog.php"><i class="bi bi-bag"></i> Explore menu</a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <h1 class="mb-2">RoastRack</h1>
      <p class="lead mb-3">Fresh off the grill. Simple, fast ordering.</p>
      <div class="d-flex flex-wrap gap-2 hero-actions">
        <a class="btn btn-light btn-lg" href="<?= $base ?>/catalog.php"><i class="bi bi-bag"></i> View Menu</a>
        <a class="btn btn-outline-light btn-lg" href="<?= $base ?>/login.php"><i class="bi bi-person"></i> Login</a>
        <a class="btn btn-outline-light btn-lg" href="<?= $base ?>/register.php"><i class="bi bi-person-plus"></i> Sign up</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($user): ?>
  <?php if (!$isStaff): ?>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="card quick-card h-100">
          <div class="card-body">
            <div class="text-muted small mb-2">Your crave list</div>
            <h5 class="card-title">Keep browsing the fire</h5>
            <p class="text-muted">See chicken parts, isaw bundles, and fresh drops while stock lasts.</p>
            <a class="btn btn-sm btn-primary" href="<?= $base ?>/catalog.php">Open Catalog</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card quick-card h-100">
          <div class="card-body">
            <div class="text-muted small mb-2">Pickup playbook</div>
            <h5 class="card-title">Reserve your slot</h5>
            <p class="text-muted">Lock a pickup time that fits your run. Add notes, free sabaw, or pay via GCash.</p>
            <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/catalog.php#reserve">Schedule Now</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card quick-card h-100">
          <div class="card-body">
            <div class="text-muted small mb-2">Latest reservation</div>
            <?php if ($lastReservation): ?>
              <h5 class="card-title">Pickup #<?= (int)$lastReservation['id']; ?></h5>
              <p class="text-muted mb-2">Status: <span class="badge text-bg-secondary"><?= htmlspecialchars($lastReservation['status']); ?></span></p>
              <p class="text-muted">Pickup <?= htmlspecialchars((new DateTime($lastReservation['pickup_at']))->format('M d, Y h:ia')); ?></p>
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/reserve_success.php?id=<?= (int)$lastReservation['id']; ?>">View details</a>
            <?php else: ?>
              <h5 class="card-title">You're all clear</h5>
              <p class="text-muted">No reservations yet. Start one now and we’ll alert you when it’s READY.</p>
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/catalog.php">Create reservation</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($isStaff): ?>
    <div class="card quick-card mt-4">
      <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <div class="text-muted small mb-1">Crew tools</div>
          <h5 class="mb-1">Jump to queue & inventory</h5>
          <p class="text-muted mb-0">Keep statuses moving, confirm payments, and tweak stocks without leaving the flow.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-primary" href="<?= $base ?>/queue.php"><i class="bi bi-receipt"></i> Manage queue</a>
          <a class="btn btn-outline-primary" href="<?= $base ?>/admin/inventory.php"><i class="bi bi-box"></i> Update inventory</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card discover-card mt-4">
    <div class="card-body">
      <h5 class="mb-3">What to explore next</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="border rounded-3 p-3 h-100">
            <h6 class="mb-2"><i class="bi bi-fire me-1"></i> Fresh skewers</h6>
            <p class="text-muted mb-0">Try seasonal marinade batches and bundle three Isaw sticks for ₱20.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded-3 p-3 h-100">
            <h6 class="mb-2"><i class="bi bi-clock-history me-1"></i> Smart pickup windows</h6>
            <p class="text-muted mb-0">Choose a slot at least 15 minutes out—perfect for commute timing.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded-3 p-3 h-100">
            <h6 class="mb-2"><i class="bi bi-droplet-half me-1"></i> Free sabaw perks</h6>
            <p class="text-muted mb-0">Toggle the free sabaw checkbox per order—staff will prep it alongside your bag.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-list"></i> Menu</h5>
          <p class="text-muted">Browse available barbecue items and deals.</p>
          <a href="<?= $base ?>/catalog.php" class="btn btn-primary">Open Catalog</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-box-seam"></i> Simple Ordering</h5>
          <p class="text-muted">Sign in to place orders. Optional free sabaw on checkout.</p>
          <a href="<?= $base ?>/login.php" class="btn btn-outline-primary">Login</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-people"></i> New here?</h5>
          <p class="text-muted">Create an account to save your details and order faster.</p>
          <a href="<?= $base ?>/register.php" class="btn btn-outline-primary">Sign up</a>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-2 mb-2">
        <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="36" height="36" style="border-radius:8px; transform:rotate(-6deg)"/>
        <h3 class="mb-0">About RoastRack</h3>
      </div>
      <p class="text-muted mb-4">RoastRack is a homegrown barbecue pickup spot. We prepare freshly grilled chicken parts and street-food favorites made to order, then notify you when it’s ready for pickup.</p>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <h5 class="mb-2"><i class="bi bi-egg-fried"></i> What we serve</h5>
            <p class="mb-2">Chicken parts and skewers you love — including Isaw with tiered pricing. “Free sabaw” on request.</p>
            <p class="mb-0 text-muted small">We only list chicken parts (no chorizo/hotdog).</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <h5 class="mb-2"><i class="bi bi-flag"></i> Our purpose</h5>
            <p class="mb-0">Make your barbecue pickup quick and organized. Reserve a time, skip the long wait, and get notified when your order is ready.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <h5 class="mb-2"><i class="bi bi-bag-check"></i> How it works</h5>
            <ul class="mb-0 small">
              <li>Browse the menu and add items.</li>
              <li>Sign in or create an account.</li>
              <li>Choose pickup time and payment.</li>
              <li>We queue your order and notify when it’s READY.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
