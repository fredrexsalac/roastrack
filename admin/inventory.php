<?php
session_start();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
if (!isset($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['admin','staff'], true)){
  header('Location: ' . ($base ?: '/') . '/admin/login.php');
  exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/gcash_accounts.php';
$pdo = db();
$isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');

// Detect optional image_path column
$hasImageCol = false;
try {
  $col = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'image_path'")->fetch();
  $hasImageCol = (bool)$col;
} catch (Throwable $e) {
  $hasImageCol = false;
}
// If missing, try to add it automatically
if (!$hasImageCol) {
  try {
    $pdo->exec("ALTER TABLE inventory_items ADD COLUMN image_path VARCHAR(255) NULL");
    $hasImageCol = true;
  } catch (Throwable $e) {
    // ignore; keep without images
  }
}

// GCash account helpers
gcash_ensure_table($pdo);
gcash_ensure_reservation_columns($pdo);

// Create / Update / Delete handlers
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  try {
    $action = $_POST['action'] ?? '';
    if ($action === 'create'){
      $name = trim($_POST['name'] ?? '');
      $desc = trim($_POST['description'] ?? '');
      $price = (float)($_POST['unit_price'] ?? 0);
      $cat = 'FINISHED';
      // Handle image upload
      $imagePath = null;
      if (!empty($_FILES['image']['name'])){
        $okTypes = ['image/jpeg','image/png','image/webp'];
        if (in_array($_FILES['image']['type'], $okTypes, true) && $_FILES['image']['error'] === UPLOAD_ERR_OK){
          $dir = __DIR__ . '/../uploads/items';
          if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
          $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
          $dest = $dir . '/' . $fname;
          if (@move_uploaded_file($_FILES['image']['tmp_name'], $dest)){
            $imagePath = (($base ?: '/') . '/uploads/items/' . $fname);
          }
        }
      }
      if ($name !== '' && $price >= 0){
        if ($hasImageCol && $imagePath){
          $stmt = $pdo->prepare('INSERT INTO inventory_items (name, description, unit_price, category, image_path) VALUES (?,?,?,?,?)');
          $stmt->execute([$name, $desc ?: null, $price, $cat, $imagePath]);
        } else {
          $stmt = $pdo->prepare('INSERT INTO inventory_items (name, description, unit_price, category) VALUES (?,?,?,?)');
          $stmt->execute([$name, $desc ?: null, $price, $cat]);
        }
      }
    }
    if ($action === 'update' && isset($_POST['id'])){
      $id = (int)$_POST['id'];
      $name = trim($_POST['name'] ?? '');
      $desc = trim($_POST['description'] ?? '');
      $price = (float)($_POST['unit_price'] ?? 0);
      // Optional new image
      $imagePath = null;
      if (!empty($_FILES['image']['name'])){
        $okTypes = ['image/jpeg','image/png','image/webp'];
        if (in_array($_FILES['image']['type'], $okTypes, true) && $_FILES['image']['error'] === UPLOAD_ERR_OK){
          $dir = __DIR__ . '/../uploads/items';
          if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
          $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
          $dest = $dir . '/' . $fname;
          if (@move_uploaded_file($_FILES['image']['tmp_name'], $dest)){
            $imagePath = (($base ?: '/') . '/uploads/items/' . $fname);
          }
        }
      }
      if ($id > 0 && $name !== '' && $price >= 0){
        if ($hasImageCol && $imagePath){
          $stmt = $pdo->prepare('UPDATE inventory_items SET name=?, description=?, unit_price=?, image_path=? WHERE id=?');
          $stmt->execute([$name, $desc ?: null, $price, $imagePath, $id]);
        } else {
          $stmt = $pdo->prepare('UPDATE inventory_items SET name=?, description=?, unit_price=? WHERE id=?');
          $stmt->execute([$name, $desc ?: null, $price, $id]);
        }
      }
    }
    if ($action === 'delete' && isset($_POST['id'])){
      $id = (int)$_POST['id'];
      if ($id > 0){
        $stmt = $pdo->prepare('DELETE FROM inventory_items WHERE id=?');
        $stmt->execute([$id]);
      }
    }
    if ($isAdmin && $action === 'gcash_create') {
      $label = trim($_POST['label'] ?? '');
      $acctName = trim($_POST['account_name'] ?? '');
      $acctNumber = trim($_POST['account_number'] ?? '');
      $instructions = trim($_POST['instructions'] ?? '');
      $verified = isset($_POST['is_verified']);
      if ($label && $acctName && $acctNumber) {
        gcash_create_account($pdo, $label, $acctName, $acctNumber, $instructions ?: null, true, $verified);
      }
    }
    if ($isAdmin && $action === 'gcash_update') {
      $id = (int)($_POST['gcash_id'] ?? 0);
      $label = trim($_POST['label'] ?? '');
      $acctName = trim($_POST['account_name'] ?? '');
      $acctNumber = trim($_POST['account_number'] ?? '');
      $instructions = trim($_POST['instructions'] ?? '');
      $active = isset($_POST['is_active']);
      $verified = isset($_POST['is_verified']);
      if ($id > 0 && $label && $acctName && $acctNumber) {
        gcash_update_account($pdo, $id, $label, $acctName, $acctNumber, $instructions ?: null, $active, $verified);
      }
    }
    if ($isAdmin && $action === 'gcash_delete') {
      $id = (int)($_POST['gcash_id'] ?? 0);
      if ($id > 0) {
        gcash_delete_account($pdo, $id);
      }
    }
    header('Location: inventory.php');
    exit;
  } catch (Throwable $e) {
    $err = 'Operation failed.';
  }
}

// Fetch items
$q = trim($_GET['q'] ?? '');
if ($q !== ''){
  $stmt = $pdo->prepare("SELECT id, name, description, unit_price, ".($hasImageCol?"image_path,":"")." category FROM inventory_items WHERE category='FINISHED' AND name LIKE ? ORDER BY name");
  $stmt->execute(['%'.$q.'%']);
} else {
  $stmt = $pdo->prepare("SELECT id, name, description, unit_price, ".($hasImageCol?"image_path,":"")." category FROM inventory_items WHERE category='FINISHED' ORDER BY name");
  $stmt->execute();
}
$items = $stmt->fetchAll();
$gcashAccounts = gcash_get_accounts($pdo, false);

include __DIR__ . '/../partials/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="d-flex align-items-center gap-2 mb-3">
      <img src="<?= $base ?>/images/roastrack.png" alt="RoastRack" width="28" height="28" class="brand-logo-tilt"/>
      <strong>RoastRack</strong>
    </div>
    <nav class="nav flex-column">
      <a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i> Home</a>
      <a class="nav-link active" href="inventory.php"><i class="bi bi-box-seam me-1"></i> Inventory</a>
      <a class="nav-link" href="<?= ($base ?: '/') ?>/queue.php"><i class="bi bi-receipt me-1"></i> Orders</a>
      <?php if ($isAdmin): ?>
      <a class="nav-link" href="customers.php"><i class="bi bi-people me-1"></i> Customers</a>
      <a class="nav-link" href="staff.php"><i class="bi bi-person-badge me-1"></i> Staff</a>
      <?php endif; ?>
      <a class="nav-link" href="notifications.php"><i class="bi bi-bell me-1"></i> Notifications</a>
    </nav>
  </aside>
  <section class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Inventory</h4>
      <form class="d-flex" method="get">
        <input class="form-control form-control-sm me-2" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search items..."/>
        <button class="btn btn-sm btn-outline-secondary">Search</button>
      </form>
    </div>

    <?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">Add New Item</h5>
        <form class="row g-2" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="create" />
          <div class="col-md-4">
            <input class="form-control" name="name" placeholder="Item name" required />
          </div>
          <div class="col-md-4">
            <input class="form-control" name="description" placeholder="Description (optional)" />
          </div>
          <div class="col-md-2">
            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" placeholder="Price" required />
          </div>
          <div class="col-md-2">
            <input type="file" class="form-control" name="image" accept="image/*" />
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-primary">Create</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Items</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <?php if ($hasImageCol): ?><th style="width:80px">Photo</th><?php endif; ?>
                <th>Name</th>
                <th>Description</th>
                <th style="width:120px">Price</th>
                <th style="width:220px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
              <tr>
                <td><?= (int)$it['id'] ?></td>
                <?php if ($hasImageCol): ?>
                  <td>
                    <?php if (!empty($it['image_path'])): ?>
                      <img src="<?= htmlspecialchars($it['image_path']) ?>" alt="thumb" class="thumb-item"/>
                    <?php else: ?>
                      <span class="text-muted small">No image</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($it['description'] ?? '') ?></td>
                <td>₱<?= htmlspecialchars(number_format((float)$it['unit_price'],2)) ?></td>
                <td class="d-flex gap-2">
                  <button
                    class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#editItemModal"
                    data-id="<?= (int)$it['id'] ?>"
                    data-name="<?= htmlspecialchars($it['name'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($it['description'] ?? '', ENT_QUOTES) ?>"
                    data-price="<?= htmlspecialchars($it['unit_price']) ?>"
                    <?php if ($hasImageCol): ?>
                    data-image="<?= htmlspecialchars($it['image_path'] ?? '') ?>"
                    <?php endif; ?>
                  >Edit</button>
                  <form method="post" onsubmit="return confirm('Delete this item?')">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$items): ?>
              <tr><td colspan="5" class="text-center text-muted">No items found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="card mt-3">
      <div class="card-body">
        <h5 class="card-title">GCash Accounts</h5>
        <form class="row g-2 mb-3" method="post">
          <input type="hidden" name="action" value="gcash_create" />
          <div class="col-md-3"><input class="form-control" name="label" placeholder="Label (e.g. Main)" required /></div>
          <div class="col-md-3"><input class="form-control" name="account_name" placeholder="Account name" required /></div>
          <div class="col-md-3"><input class="form-control" name="account_number" placeholder="Account number" required /></div>
          <div class="col-md-3"><input class="form-control" name="instructions" placeholder="Instructions (optional)" /></div>
          <div class="col-md-3">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="is_verified" id="gcashCreateVerified" />
              <label class="form-check-label small" for="gcashCreateVerified">Mark as verified (shows trusted badge)</label>
            </div>
          </div>
          <div class="col-12 d-grid d-md-block"><button class="btn btn-primary">Add GCash</button></div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Label</th>
                <th>Name</th>
                <th>Number</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gcashAccounts as $acct): ?>
                <tr>
                  <td><?= htmlspecialchars($acct['label']) ?></td>
                  <td><?= htmlspecialchars($acct['account_name']) ?></td>
                  <td><?= htmlspecialchars($acct['account_number']) ?></td>
                  <td>
                    <?php if ($acct['is_active']): ?><span class="badge text-bg-success">Active</span><?php else: ?><span class="badge text-bg-secondary">Hidden</span><?php endif; ?>
                    <?php if (!empty($acct['is_verified'])): ?><span class="badge text-bg-primary ms-1"><i class="bi bi-shield-check"></i> Verified</span><?php endif; ?>
                  </td>
                  <td class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGcashModal" data-id="<?= (int)$acct['id'] ?>" data-label="<?= htmlspecialchars($acct['label'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($acct['account_name'], ENT_QUOTES) ?>" data-number="<?= htmlspecialchars($acct['account_number'], ENT_QUOTES) ?>" data-instructions="<?= htmlspecialchars($acct['instructions'] ?? '', ENT_QUOTES) ?>" data-active="<?= (int)$acct['is_active'] ?>" data-verified="<?= (int)$acct['is_verified'] ?>">Edit</button>
                    <form method="post" onsubmit="return confirm('Delete this GCash account?');">
                      <input type="hidden" name="action" value="gcash_delete" />
                      <input type="hidden" name="gcash_id" value="<?= (int)$acct['id'] ?>" />
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$gcashAccounts): ?><tr><td colspan="5" class="text-center text-muted">No GCash accounts configured.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>

<!-- Edit GCash Modal -->
<div class="modal fade" id="editGcashModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="gcash_update" />
        <input type="hidden" name="gcash_id" id="gcash-id" />
        <div class="modal-header">
          <h5 class="modal-title">Edit GCash Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Label</label>
            <input class="form-control" name="label" id="gcash-label" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Account name</label>
            <input class="form-control" name="account_name" id="gcash-name" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Account number</label>
            <input class="form-control" name="account_number" id="gcash-number" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Instructions</label>
            <textarea class="form-control" name="instructions" id="gcash-instructions" rows="2"></textarea>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="gcash-active" />
            <label class="form-check-label" for="gcash-active">Active</label>
          </div>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_verified" id="gcash-verified" />
            <label class="form-check-label" for="gcash-verified">Verified (enables direct GCash link)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="action" value="update" />
          <input type="hidden" name="id" id="edit-id" />
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="edit-name" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input class="form-control" name="description" id="edit-description" />
          </div>
          <div class="mb-3">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" id="edit-price" required />
          </div>
          <?php if ($hasImageCol): ?>
          <div class="mb-3">
            <label class="form-label">Photo (optional)</label>
            <input type="file" class="form-control" name="image" accept="image/*" />
            <div class="mt-2 small text-muted">Current: <span id="edit-image-text">None</span></div>
            <img id="edit-image-preview" class="thumb-item mt-2 d-none" alt="preview" />
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
 </div>

<script>
  const gcashModal = document.getElementById('editGcashModal');
  if (gcashModal) {
    gcashModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      gcashModal.querySelector('#gcash-id').value = button.getAttribute('data-id') || '';
      gcashModal.querySelector('#gcash-label').value = button.getAttribute('data-label') || '';
      gcashModal.querySelector('#gcash-name').value = button.getAttribute('data-name') || '';
      gcashModal.querySelector('#gcash-number').value = button.getAttribute('data-number') || '';
      gcashModal.querySelector('#gcash-instructions').value = button.getAttribute('data-instructions') || '';
      const active = button.getAttribute('data-active') === '1';
      const verified = button.getAttribute('data-verified') === '1';
      gcashModal.querySelector('#gcash-active').checked = active;
      const verifiedInput = gcashModal.querySelector('#gcash-verified');
      if (verifiedInput) {
        verifiedInput.checked = verified;
      }
    });
  }

  const editModal = document.getElementById('editItemModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      const id = button.getAttribute('data-id');
      const name = button.getAttribute('data-name') || '';
      const desc = button.getAttribute('data-description') || '';
      const price = button.getAttribute('data-price') || '';
      const img = button.getAttribute('data-image') || '';

      editModal.querySelector('#edit-id').value = id;
      editModal.querySelector('#edit-name').value = name;
      editModal.querySelector('#edit-description').value = desc;
      editModal.querySelector('#edit-price').value = price;
      const imgText = editModal.querySelector('#edit-image-text');
      if (imgText) imgText.textContent = img ? img : 'None';
    });
    // bind preview on file change
    editModal.addEventListener('change', function (e) {
      if (e.target && e.target.matches('input[type=file][name=image]')){
        const file = e.target.files && e.target.files[0];
        const prev = editModal.querySelector('#edit-image-preview');
        if (file && prev){
          prev.src = URL.createObjectURL(file);
          prev.classList.remove('d-none');
        }
      }
    });
  }
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
