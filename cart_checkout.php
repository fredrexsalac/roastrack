<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/cart_helpers.php';
require_once __DIR__ . '/helpers/gcash_accounts.php';
$pdo = db();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '' || $base === '/' || $base === '\\') { $base = ''; }

if (!isset($_SESSION['user'])) {
  header('Location: ' . ($base ?: '/') . '/login.php');
  exit;
}

$cart = rr_get_cart();
if (!$cart) {
  header('Location: ' . ($base ?: '/') . '/catalog.php?msg=' . urlencode('Your cart is empty.'));
  exit;
}

$gcashAccounts = gcash_get_accounts($pdo);
$gcashLogoUrl = ($base ?: '/') . '/assets/payment-img/gcash.jpg';

function rr_checkout_items(PDO $pdo, array $cart): array {
  $items = [];
  $total = 0;
  if (!$cart) return [$items, $total];
  $ids = array_keys($cart);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT id, name, unit_price FROM inventory_items WHERE id IN ($placeholders)");
  $stmt->execute($ids);
  $map = [];
  while ($row = $stmt->fetch()) {
    $map[$row['id']] = $row;
  }
  foreach ($cart as $id => $qty) {
    $qty = (int)$qty;
    if ($qty <= 0 || !isset($map[$id])) continue;
    $row = $map[$id];
    $row['qty'] = $qty;
    $row['line_total'] = $qty * $row['unit_price'];
    $items[] = $row;
    $total += $row['line_total'];
  }
  return [$items, $total];
}

[$cartItems, $cartTotal] = rr_checkout_items($pdo, $cart);
if (!$cartItems) {
  rr_cart_clear();
  header('Location: ' . ($base ?: '/') . '/catalog.php?msg=' . urlencode('Items in your cart are no longer available. Please add them again.'));
  exit;
}

$errors = [];
$pickup_at_raw = trim($_POST['pickup_at'] ?? '');
$minPickupVal = (new DateTime('+15 minutes'))->format('Y-m-d\TH:i');
$maxPickupVal = (new DateTime('+3 days'))->format('Y-m-d\TH:i');
$notes = trim($_POST['notes'] ?? '');
$free_sabaw = isset($_POST['free_sabaw']) ? 1 : 0;
$payment_method = ($_POST['payment_method'] ?? 'PICKUP') === 'GCASH' ? 'GCASH' : 'PICKUP';
$requiresProof = ($payment_method === 'GCASH');
$selectedAccountId = isset($_POST['gcash_account_id']) ? (int)$_POST['gcash_account_id'] : 0;
$selectedAccount = null;
foreach ($gcashAccounts as $acct) {
  if ((int)$acct['id'] === $selectedAccountId) {
    $selectedAccount = $acct;
    break;
  }
}
$user = $_SESSION['user'];
$customer_name = $user['full_name'] ?? $user['username'] ?? null;
$customer_phone = $user['phone'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($pickup_at_raw === '') {
    $errors[] = 'Pickup time is required.';
  }
  $pickup_at = null;
  if (!$errors) {
    try {
      $pickup_at = new DateTime($pickup_at_raw);
      $now = new DateTime('now');
      $min = (clone $now)->modify('+15 minutes');
      $max = (clone $now)->modify('+3 days');
      if ($pickup_at < $min) {
        $errors[] = 'Pickup time must be at least 15 minutes from now.';
      } elseif ($pickup_at > $max) {
        $errors[] = 'Pickup time can only be scheduled within the next 3 days.';
      }
    } catch (Throwable $e) {
      $errors[] = 'Invalid pickup time.';
    }
  }

  $gcash_proof_path = null;
  if ($requiresProof) {
    if (!$selectedAccount) {
      $errors[] = 'Select a GCash account to pay.';
    }
    if (!isset($_FILES['gcash_proof']) || $_FILES['gcash_proof']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'GCash proof is required. Upload a valid screenshot.';
    } else {
      $tmp = $_FILES['gcash_proof']['tmp_name'];
      $ext = strtolower(pathinfo($_FILES['gcash_proof']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
        $errors[] = 'Invalid image type for GCash proof.';
      } else {
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $fname = 'gcash_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . '/' . $fname;
        if (!move_uploaded_file($tmp, $dest)) {
          $errors[] = 'Failed to save GCash proof.';
        } else {
          $gcash_proof_path = ($base ?: '/') . '/uploads/' . $fname;
        }
      }
    }
  }

  if (!$errors && !$cartItems) {
    $errors[] = 'Your cart is empty.';
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();
      $gcashLabel = $selectedAccount['label'] ?? null;
      $gcashNumber = $selectedAccount['account_number'] ?? null;
      $ins = $pdo->prepare('INSERT INTO reservations (user_id, customer_name, customer_phone, status, pickup_at, notes, payment_method, gcash_proof_path, gcash_account_label, gcash_account_number) VALUES (?,?,?,?,?,?,?,?,?,?)');
      $ins->execute([
        $user['id'], $customer_name, $customer_phone, 'PENDING', $pickup_at->format('Y-m-d H:i:s'), $notes, $payment_method, $gcash_proof_path, $gcashLabel, $gcashNumber
      ]);
      $resId = (int)$pdo->lastInsertId();
      $insItem = $pdo->prepare('INSERT INTO reservation_items (reservation_id, item_id, item_name, qty, unit_price) VALUES (?,?,?,?,?)');
      foreach ($cartItems as $item) {
        $name = $item['name'];
        $qty = (int)$item['qty'];
        if ($qty <= 0) continue;
        if (stripos($name, 'isaw') !== false) {
          $groups = intdiv($qty, 3);
          $rem = $qty % 3;
          if ($groups > 0) {
            $insItem->execute([$resId, $item['id'], $name . ' (3s set)', $groups, 20.00]);
          }
          if ($rem > 0) {
            $insItem->execute([$resId, $item['id'], $name . ' (single)', $rem, 10.00]);
          }
        } else {
          $insItem->execute([$resId, $item['id'], $name, $qty, $item['unit_price']]);
        }
      }
      if ($free_sabaw) {
        $insItem->execute([$resId, 0, 'Free Sabaw', 1, 0]);
      }
      $pdo->commit();
      rr_cart_clear();
      header('Location: ' . ($base ?: '/') . '/reserve_success.php?id=' . $resId);
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Failed to place reservation. Please try again.';
    }
  }
}

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
  .gcash-card small { color: rgba(255,255,255,0.8); }
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
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="mb-3">Reserve your cart</h4>
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Pickup time</label>
            <input type="datetime-local" class="form-control" name="pickup_at" min="<?= $minPickupVal ?>" max="<?= $maxPickupVal ?>" value="<?= htmlspecialchars($pickup_at_raw ?: '') ?>" required />
            <small class="text-primary">Pick a time 15 minutes ahead and within the next 3 days.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment method</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="payPickup" value="PICKUP" <?= $payment_method === 'PICKUP' ? 'checked' : '' ?>>
              <label class="form-check-label" for="payPickup">Pay at pickup</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="payGCash" value="GCASH" <?= $payment_method === 'GCASH' ? 'checked' : '' ?> <?= $gcashAccounts ? '' : 'disabled' ?>>
              <label class="form-check-label" for="payGCash">GCash (upload proof)</label>
              <?php if (!$gcashAccounts): ?><div class="text-danger small">No GCash accounts configured. Ask admin.</div><?php endif; ?>
            </div>
          </div>
          <div class="mb-3 <?= ($payment_method === 'GCASH' && $gcashAccounts) ? '' : 'd-none' ?>" id="gcashProofWrap">
            <?php if ($gcashAccounts): ?>
              <label class="form-label">Choose a GCash account</label>
              <select class="form-select mb-3" name="gcash_account_id" id="gcashAccountSelect">
                <?php foreach ($gcashAccounts as $acct): ?>
                  <option value="<?= (int)$acct['id'] ?>"
                    data-label="<?= htmlspecialchars($acct['label'], ENT_QUOTES) ?>"
                    data-name="<?= htmlspecialchars($acct['account_name'], ENT_QUOTES) ?>"
                    data-number="<?= htmlspecialchars($acct['account_number'], ENT_QUOTES) ?>"
                    data-instructions="<?= htmlspecialchars($acct['instructions'] ?? '', ENT_QUOTES) ?>"
                    <?= ($selectedAccountId === (int)$acct['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($acct['label']) ?> (<?= htmlspecialchars($acct['account_number']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="gcash-card mb-3" id="gcashAccountDetails">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="gcash-chip"><i class="bi bi-wallet2"></i> GCash</span>
                  <img src="<?= htmlspecialchars($gcashLogoUrl) ?>" alt="GCash" class="gcash-logo" />
                </div>
                <div class="small fw-semibold text-white-50" id="gcashAccountLabel"></div>
                <div class="text-uppercase small text-white-50">Send payment to</div>
                <div class="fs-5 fw-bold" id="gcashAccountName"></div>
                <div class="fs-6" id="gcashAccountNumber"></div>
                <small id="gcashInstructions"></small>
              </div>
            <?php endif; ?>
            <label class="form-label">Upload GCash proof (screenshot/photo)</label>
            <input type="file" class="form-control" name="gcash_proof" accept="image/*" <?= $payment_method === 'GCASH' ? 'required' : '' ?>>
            <small class="text-muted">Invalid or missing proof will be rejected.</small>
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" value="1" id="freeSabaw" name="free_sabaw" <?= $free_sabaw ? 'checked' : '' ?>>
            <label class="form-check-label" for="freeSabaw">Add free sabaw</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="No spicy, extra sauce, etc."><?= htmlspecialchars($notes) ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= $base ?>/catalog.php">Back to catalog</a>
            <button class="btn btn-primary flex-grow-1">Place reservation</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Cart summary</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cartItems as $ci): ?>
                <tr>
                  <td><?= htmlspecialchars($ci['name']) ?></td>
                  <td class="text-end">× <?= (int)$ci['qty'] ?></td>
                  <td class="text-end">₱<?= number_format((float)$ci['line_total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <strong>Subtotal</strong>
          <span class="h5 mb-0">₱<?= number_format((float)$cartTotal, 2) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  const payPickup = document.getElementById('payPickup');
  const payGCash = document.getElementById('payGCash');
  const proofWrap = document.getElementById('gcashProofWrap');
  const proofInput = proofWrap?.querySelector('input[name="gcash_proof"]');
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
    acctInstr.textContent = opt.dataset.instructions || 'Use the real GCash app. Proofs are reviewed by admins before approval.';
  }

  function toggleProof(){
    if (!proofWrap) return;
    if (payGCash.checked && acctSelect) {
      proofWrap.classList.remove('d-none');
      if (proofInput) proofInput.setAttribute('required','required');
      acctSelect.setAttribute('required','required');
      populateAccountDetails();
    } else {
      proofWrap.classList.add('d-none');
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
