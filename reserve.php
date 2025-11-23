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

// Require login
if (!isset($_SESSION['user'])){
  header('Location: ' . $base . '/login.php');
  exit;
}

$itemId = isset($_GET['item']) ? (int)$_GET['item'] : 0;
if ($itemId <= 0) {
  header('Location: ' . $base . '/catalog.php?msg=' . urlencode('Select an item to reserve.'));
  exit;
}

$stmt = $pdo->prepare('SELECT id, name, description, unit_price FROM inventory_items WHERE id = ? AND category = "FINISHED"');
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) {
  header('Location: ' . $base . '/catalog.php?msg=' . urlencode('Item not found.'));
  exit;
}

$gcashAccounts = gcash_get_accounts($pdo);
$gcashLogoUrl = $base . '/assets/payment-img/gcash.jpg';
$hasGcashAccounts = !empty($gcashAccounts);
$minPickup = (new DateTime('+15 minutes'))->format('Y-m-d\TH:i');
$maxPickup = (new DateTime('+3 days'))->format('Y-m-d\TH:i');

include __DIR__ . '/partials/header.php';
?>
<style>
  .gcash-card {
    background: linear-gradient(135deg, #0160d6, #00a2ff);
    border-radius: 14px;
    color: #fff;
    padding: 1rem;
    box-shadow: 0 0.75rem 1.5rem rgba(1, 96, 214, 0.35);
  }
  .gcash-card small {
    color: rgba(255,255,255,0.8);
  }
  .gcash-logo {
    width: 80px;
    max-width: 40%;
    background: rgba(255,255,255,0.9);
    border-radius: 10px;
    padding: .35rem .6rem;
  }
  .gcash-chip {
    background: rgba(255,255,255,0.15);
    border-radius: 999px;
    font-size: .75rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .2rem .85rem;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
  }
</style>
<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-warning"><?php echo htmlspecialchars($_GET['msg']); ?></div>
<?php endif; ?>
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Reserve for pickup</h4>
        <form method="post" action="<?= $base ?>/reserve_submit.php" enctype="multipart/form-data">
          <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>" />
          <div class="mb-3">
            <label class="form-label">Item</label>
            <input class="form-control" value="<?= htmlspecialchars($item['name']) ?> (₱<?= number_format((float)$item['unit_price'],2) ?>)" disabled />
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control" name="qty" value="1" min="1" required />
          </div>
          <!-- Logged-in user info comes from account -->
          <div class="mb-3">
            <label class="form-label">Pickup time</label>
            <input type="datetime-local" class="form-control" name="pickup_at" min="<?= $minPickup ?>" max="<?= $maxPickup ?>" required />
            <small class="text-primary">Choose a slot at least 15 minutes from now and within the next 3 days.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment method</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="payPickup" value="PICKUP" checked>
              <label class="form-check-label" for="payPickup">Pickup Reservation (pay at pickup)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="payGCash" value="GCASH" <?= $hasGcashAccounts ? '' : 'disabled' ?>>
              <label class="form-check-label" for="payGCash">GCash (upload proof)</label>
              <?php if (!$hasGcashAccounts): ?><div class="text-danger small">No GCash accounts configured yet.</div><?php endif; ?>
            </div>
          </div>
          <div class="mb-3 d-none" id="gcashProofWrap">
            <?php if ($hasGcashAccounts): ?>
              <label class="form-label">Choose a GCash account</label>
              <select class="form-select mb-3" name="gcash_account_id" id="gcashAccountSelect">
                <?php foreach ($gcashAccounts as $acct): ?>
                  <option value="<?= (int)$acct['id'] ?>"
                    data-label="<?= htmlspecialchars($acct['label'], ENT_QUOTES) ?>"
                    data-label="<?= htmlspecialchars($acct['label'], ENT_QUOTES) ?>"
                    data-name="<?= htmlspecialchars($acct['account_name'], ENT_QUOTES) ?>"
                    data-number="<?= htmlspecialchars($acct['account_number'], ENT_QUOTES) ?>"
                    data-instructions="<?= htmlspecialchars($acct['instructions'] ?? '', ENT_QUOTES) ?>">
                    <?= htmlspecialchars($acct['label']) ?> (<?= htmlspecialchars($acct['account_number']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="gcash-card mb-3" id="gcashAccountDetails">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="gcash-chip"><i class="bi bi-wallet2"></i> GCash</span>
                  <img src="<?= htmlspecialchars($gcashLogoUrl) ?>" alt="GCash logo" class="gcash-logo" />
                </div>
                <div class="small fw-semibold text-white-50" id="gcashAccountLabel"></div>
                <div class="text-uppercase small text-white-50">Send payment to</div>
                <div class="fs-5 fw-bold" id="gcashAccountName"></div>
                <div class="fs-6" id="gcashAccountNumber"></div>
                <small id="gcashInstructions"></small>
              </div>
            <?php endif; ?>
            <label class="form-label">Upload GCash proof (screenshot/photo)</label>
            <input type="file" class="form-control" name="gcash_proof" accept="image/*">
            <small class="text-muted">Proof is required for GCash reservations and subject to admin approval.</small>
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" value="1" id="freeSabaw" name="free_sabaw">
            <label class="form-check-label" for="freeSabaw">Add free sabaw</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="No spicy, extra sauce, etc."></textarea>
          </div>
          <div class="d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="<?= $base ?>/catalog.php">Back to catalog</a>
            <button class="btn btn-primary">Place reservation</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
  const payPickup = document.getElementById('payPickup');
  const payGCash = document.getElementById('payGCash');
  const proof = document.getElementById('gcashProofWrap');
  const proofInput = proof?.querySelector('input[name="gcash_proof"]');
  const acctSelect = document.getElementById('gcashAccountSelect');
  const acctLabel = document.getElementById('gcashAccountLabel');
  const acctName = document.getElementById('gcashAccountName');
  const acctNumber = document.getElementById('gcashAccountNumber');
  const acctInstr = document.getElementById('gcashInstructions');

  function populateAccountDetails(){
    if (!acctSelect) return;
    const opt = acctSelect.options[acctSelect.selectedIndex];
    if (!opt) return;
    acctLabel.textContent = opt.dataset.label || 'GCash account';
    acctName.textContent = opt.dataset.name || '';
    acctNumber.textContent = opt.dataset.number ? `GCash: ${opt.dataset.number}` : '';
    acctInstr.textContent = opt.dataset.instructions || 'Attach an actual GCash screenshot showing this number. Non-GCash or edited uploads will be rejected by admins.';
  }

  function toggleProof(){
    if (payGCash.checked && acctSelect) {
      proof.classList.remove('d-none');
      if (proofInput) proofInput.setAttribute('required', 'required');
      acctSelect.setAttribute('required','required');
      populateAccountDetails();
    } else {
      proof.classList.add('d-none');
      if (proofInput) proofInput.removeAttribute('required');
      acctSelect?.removeAttribute('required');
    }
  }
  payPickup.addEventListener('change', toggleProof);
  payGCash.addEventListener('change', toggleProof);
  acctSelect?.addEventListener('change', populateAccountDetails);
  toggleProof();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
