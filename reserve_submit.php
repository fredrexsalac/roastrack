<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/gcash_accounts.php';
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
  header('Location: ' . $base . '/catalog.php');
  exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 1);
$pickup_at_raw = trim($_POST['pickup_at'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$free_sabaw = isset($_POST['free_sabaw']) ? 1 : 0;
$payment_method = ($_POST['payment_method'] ?? 'PICKUP') === 'GCASH' ? 'GCASH' : 'PICKUP';
$gcash_proof_path = null;
$gcash_account_id = isset($_POST['gcash_account_id']) ? (int)$_POST['gcash_account_id'] : 0;

if ($item_id <= 0 || $qty <= 0 || $pickup_at_raw === ''){
  header('Location: ' . $base . '/catalog.php?msg=' . urlencode('Invalid reservation details.'));
  exit;
}

// Validate pickup time (minimum lead time 15 minutes)
try {
  $pickup_at = new DateTime($pickup_at_raw);
  $now = new DateTime('now');
  $min = (clone $now)->modify('+15 minutes');
  $max = (clone $now)->modify('+3 days');
  if ($pickup_at < $min) {
    header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Pickup time must be at least 15 minutes from now.'));
    exit;
  }
  if ($pickup_at > $max) {
    header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Pickup time can only be scheduled within the next 3 days.'));
    exit;
  }
} catch (Throwable $e) {
  header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Invalid pickup time.'));
  exit;
}

// Fetch item
$stmt = $pdo->prepare('SELECT id, name, unit_price FROM inventory_items WHERE id=? AND category="FINISHED"');
$stmt->execute([$item_id]);
$item = $stmt->fetch();
if (!$item){
  header('Location: ' . $base . '/catalog.php?msg=' . urlencode('Item not found.'));
  exit;
}

$requiresProof = ($payment_method === 'GCASH');
$selectedAccount = null;
if ($requiresProof) {
  if ($gcash_account_id <= 0) {
    header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Choose a GCash account before submitting.'));
    exit;
  }
  $pdoConn = db();
  $selectedAccount = gcash_get_account($pdoConn, $gcash_account_id);
  if (!$selectedAccount || !$selectedAccount['is_active']) {
    header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Selected GCash account is unavailable. Please pick another.'));
    exit;
  }
  if (!empty($_FILES['gcash_proof']['name']) && $_FILES['gcash_proof']['error'] === UPLOAD_ERR_OK) {
    try {
      $tmp = $_FILES['gcash_proof']['tmp_name'];
      $ext = strtolower(pathinfo($_FILES['gcash_proof']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
        header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Invalid image type for GCash proof.'));
        exit;
      }
      $uploadDir = __DIR__ . '/uploads';
      if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
      $fname = 'gcash_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $dest = $uploadDir . '/' . $fname;
      if (!move_uploaded_file($tmp, $dest)) {
        header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Failed to save GCash proof. Please try again.'));
        exit;
      }
      $gcash_proof_path = $base . '/uploads/' . $fname;
    } catch (Throwable $e) {
      header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Unable to process GCash proof. Please try again.'));
      exit;
    }
  }
}

$pdo->beginTransaction();
try {
  $user_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
  $customer_name = null;
  $customer_phone = null;

  // Insert reservation
  $gcashLabel = $selectedAccount['label'] ?? null;
  $gcashNumber = $selectedAccount['account_number'] ?? null;
  $ins = $pdo->prepare('INSERT INTO reservations (user_id, customer_name, customer_phone, status, pickup_at, notes, payment_method, gcash_proof_path, gcash_account_label, gcash_account_number) VALUES (?,?,?,?,?,?,?,?,?,?)');
  $ins->execute([
    $user_id, $customer_name, $customer_phone, 'PENDING', $pickup_at->format('Y-m-d H:i:s'), $notes, $payment_method, $gcash_proof_path, $gcashLabel, $gcashNumber
  ]);
  $res_id = (int)$pdo->lastInsertId();

  // Insert items with special pricing rules for Isaw
  $insItem = $pdo->prepare('INSERT INTO reservation_items (reservation_id, item_id, item_name, qty, unit_price) VALUES (?,?,?,?,?)');
  $isIsaw = stripos($item['name'], 'isaw') !== false;
  if ($isIsaw) {
    $groups = intdiv($qty, 3);     // wholesale sets of 3
    $rem = $qty % 3;               // singles
    if ($groups > 0) {
      $insItem->execute([$res_id, $item['id'], $item['name'] . ' (3s set)', $groups, 20.00]);
    }
    if ($rem > 0) {
      $insItem->execute([$res_id, $item['id'], $item['name'] . ' (single)', $rem, 10.00]);
    }
  } else {
    $insItem->execute([$res_id, $item['id'], $item['name'], $qty, $item['unit_price']]);
  }

  // Optional free sabaw
  if ($free_sabaw){
    $insItem->execute([$res_id, 0, 'Free Sabaw', 1, 0]);
  }

  $pdo->commit();
  header('Location: ' . $base . '/reserve_success.php?id=' . $res_id);
  exit;
} catch (Throwable $e) {
  $pdo->rollBack();
  header('Location: ' . $base . '/reserve.php?item=' . $item_id . '&msg=' . urlencode('Failed to create reservation.'));
  exit;
}
