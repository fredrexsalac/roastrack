<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/cart_helpers.php';
$pdo = db();

// Detect if inventory_items has image_path column so we can display uploaded photos
$hasImageCol = false;
try {
  $col = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'image_path'")->fetch();
  $hasImageCol = (bool)$col;
} catch (Throwable $e) {
  $hasImageCol = false;
}

$cart = rr_get_cart();
$cartCount = rr_cart_count();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if (!isset($_SESSION['user'])) {
    header('Location: ' . ($base ?? '/') . '/login.php');
    exit;
  }
  if ($action === 'add' && isset($_POST['item_id'])) {
    rr_cart_add((int)$_POST['item_id'], (int)($_POST['qty'] ?? 1));
    $msg = 'Item added to cart.';
  } elseif ($action === 'update' && isset($_POST['item_id'])) {
    rr_cart_set((int)$_POST['item_id'], (int)($_POST['qty'] ?? 1));
    $msg = 'Cart updated.';
  } elseif ($action === 'remove' && isset($_POST['item_id'])) {
    rr_cart_remove((int)$_POST['item_id']);
    $msg = 'Item removed from cart.';
  } elseif ($action === 'clear') {
    rr_cart_clear();
    $msg = 'Cart cleared.';
  }
  $cart = rr_get_cart();
  $cartCount = rr_cart_count();
}

// Fetch FINISHED items (include image path when column available)
$selectCols = 'id, name, description, unit_price';
if ($hasImageCol) { $selectCols .= ', image_path'; }
$stmt = $pdo->prepare("SELECT $selectCols FROM inventory_items WHERE category='FINISHED' ORDER BY name");
$stmt->execute();
$items = $stmt->fetchAll();

include __DIR__ . '/partials/header.php';
?>
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-start mb-4">
  <div class="flex-grow-1 text-center">
    <div class="mb-2">
      <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="56" height="56" class="logo-tilt-sm mx-auto d-block"/>
    </div>
    <h1 class="display-6 title-strong mb-1">RoastRack Menu</h1>
    <p class="text-muted mb-0">Add your cravings to the cart, then reserve a pickup time.</p>
    <?php if ($msg): ?><div class="alert alert-success mt-3 mx-auto" style="max-width:480px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  </div>
  <?php if (isset($_SESSION['user'])): ?>
    <div class="card shadow-sm" style="min-width:260px;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <strong>Cart</strong>
          <span class="badge text-bg-primary"><?= $cartCount ?></span>
        </div>
        <p class="text-muted small mb-3">Keep adding items, then reserve everything at once.</p>
        <a class="btn btn-sm btn-primary w-100" href="#orderSummary" <?= $cartCount ? '' : 'disabled' ?>>View Cart & Reserve</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (!$items): ?>
  <div class="alert alert-info">No items available.</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($items as $item): ?>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <?php if ($hasImageCol && !empty($item['image_path'])): ?>
            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="img-fluid rounded mb-2 catalog-item-img" />
          <?php endif; ?>
          <h5 class="card-title mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
          <?php if (!empty($item['description'])): ?>
            <p class="card-text text-muted small flex-grow-1"><?php echo htmlspecialchars($item['description']); ?></p>
          <?php else: ?>
            <div class="flex-grow-1"></div>
          <?php endif; ?>
          <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="h5 mb-0">₱<?php echo number_format((float)$item['unit_price'], 2); ?></div>
            <?php if (isset($_SESSION['user'])): ?>
              <form class="d-flex gap-2" method="post">
                <input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>" />
                <input type="hidden" name="action" value="add" />
                <input type="number" name="qty" value="1" min="1" max="<?= RR_CART_MAX_QTY ?>" class="form-control form-control-sm" style="width:80px;" />
                <button class="btn btn-primary">Add</button>
              </form>
            <?php else: ?>
              <a class="btn btn-primary" href="<?= $base ?>/reserve.php?item=<?php echo (int)$item['id']; ?>">Reserve</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (isset($_SESSION['user'])): ?>
  <?php
    $cartItemIds = array_keys($cart);
    $cartItems = [];
    $cartTotal = 0;
    if ($cartItemIds) {
      $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
      $stmtItems = $pdo->prepare("SELECT id, name, unit_price FROM inventory_items WHERE id IN ($placeholders)");
      $stmtItems->execute($cartItemIds);
      while ($row = $stmtItems->fetch()) {
        $qty = $cart[$row['id']] ?? 0;
        if ($qty <= 0) continue;
        $row['qty'] = $qty;
        $row['line_total'] = $qty * $row['unit_price'];
        $cartTotal += $row['line_total'];
        $cartItems[] = $row;
      }
    }
  ?>
  <div class="card shadow-sm mt-4" id="orderSummary">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Cart summary</h4>
        <?php if ($cartItems): ?><form method="post"><input type="hidden" name="action" value="clear" /><button class="btn btn-sm btn-outline-danger" type="submit">Clear</button></form><?php endif; ?>
      </div>
      <?php if (!$cartItems): ?>
        <div class="alert alert-info mb-0">Your cart is empty. Add items above to start a reservation.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cartItems as $ci): ?>
                <tr>
                  <td><?= htmlspecialchars($ci['name']) ?></td>
                  <td style="width:120px;">
                    <form class="d-flex gap-2" method="post">
                      <input type="hidden" name="action" value="update" />
                      <input type="hidden" name="item_id" value="<?= (int)$ci['id']; ?>" />
                      <input type="number" class="form-control form-control-sm" name="qty" value="<?= (int)$ci['qty']; ?>" min="1" max="<?= RR_CART_MAX_QTY ?>" />
                      <button class="btn btn-sm btn-outline-secondary">Set</button>
                    </form>
                  </td>
                  <td>₱<?= number_format((float)$ci['unit_price'], 2) ?></td>
                  <td>₱<?= number_format((float)$ci['line_total'], 2) ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="remove" />
                      <input type="hidden" name="item_id" value="<?= (int)$ci['id']; ?>" />
                      <button class="btn btn-sm btn-link text-danger" type="submit">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="fw-bold">Subtotal</div>
          <div class="h4 mb-0">₱<?= number_format((float)$cartTotal, 2) ?></div>
        </div>
        <div class="text-end mt-3">
          <a class="btn btn-primary btn-lg" href="<?= $base ?>/cart_checkout.php">Reserve these items</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
