<?php
$pageTitle = 'Products';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Add color to product ─────────────────────────────────────────────────────────
if ($action === 'add_color' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)($_POST['product_id'] ?? 0);
    $color = sanitize($conn, trim($_POST['color_name'] ?? ''));
    if ($pid && $color) {
        $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name) VALUES (?,?)");
        $stmt->bind_param('is', $pid, $color);
        $stmt->execute();
        flash('success', "Color \"$color\" added.");
    }
    redirect(BASE_URL . "/products.php?action=edit&id=$pid");
}

// ── Delete color ─────────────────────────────────────────────────────────────────
if ($action === 'delete_color' && $id) {
    $row = $conn->query("SELECT product_id FROM product_colors WHERE id=$id LIMIT 1")->fetch_assoc();
    $conn->query("DELETE FROM product_colors WHERE id=$id");
    flash('success', 'Color removed.');
    redirect(BASE_URL . "/products.php?action=edit&id=" . ($row['product_id'] ?? ''));
}

// ── Delete ──────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("UPDATE products SET active=0 WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Product removed.');
    redirect(BASE_URL . '/products.php');
}

// ── Save (Add/Edit) ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid      = (int)($_POST['id'] ?? 0);
    $name     = sanitize($conn, $_POST['name'] ?? '');
    $cat_id   = (int)($_POST['category_id'] ?? 0) ?: 'NULL';
    $sku      = sanitize($conn, $_POST['sku']  ?? '');
    $desc     = sanitize($conn, $_POST['description'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $cost     = (float)($_POST['cost']  ?? 0);
    $stock    = (int)($_POST['stock']   ?? 0);
    $unit     = sanitize($conn, $_POST['unit'] ?? 'pcs');

    if (!$name) {
        flash('danger', 'Product name is required.');
        redirect(BASE_URL . '/products.php?action=' . ($pid ? 'edit&id=' . $pid : 'add'));
    }

    if ($pid) {
        $stmt = $conn->prepare("UPDATE products SET category_id=?,name=?,sku=?,description=?,price=?,cost=?,stock=?,unit=? WHERE id=?");
        $stmt->bind_param('isssddisi', $cat_id, $name, $sku, $desc, $price, $cost, $stock, $unit, $pid);
        $stmt->execute();
        flash('success', 'Product updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO products (category_id,name,sku,description,price,cost,stock,unit) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isssddis', $cat_id, $name, $sku, $desc, $price, $cost, $stock, $unit);
        $stmt->execute();
        flash('success', 'Product added.');
    }
    redirect(BASE_URL . '/products.php');
}

// ── Fetch categories ─────────────────────────────────────────────────────────────
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ── Add / Edit Form ──────────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $prod = ['id'=>0,'category_id'=>'','name'=>'','sku'=>'','description'=>'','price'=>'','cost'=>'','stock'=>'','unit'=>'pcs'];
    if ($action === 'edit' && $id) {
        $r = $conn->query("SELECT * FROM products WHERE id=$id LIMIT 1");
        if ($r->num_rows) $prod = $r->fetch_assoc();
    }
    $formTitle = $action === 'edit' ? 'Edit Product' : 'Add Product';
    ?>
    <div class="container-fluid">
      <div class="d-flex align-items-center mb-4 gap-3">
        <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        <h5 class="mb-0 fw-bold"><?= $formTitle ?></h5>
      </div>
      <div class="row">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-body p-4">
              <form method="POST">
                <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                <div class="row g-3">

                  <div class="col-12">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($prod['name']) ?>" required placeholder="e.g. Blue Denim Jacket">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                      <option value="">— None —</option>
                      <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $prod['category_id']==$cat['id'] ? 'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">SKU / Barcode</label>
                    <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($prod['sku']) ?>" placeholder="e.g. JKT-001">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Selling Price (<?= CURRENCY ?>)</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= $prod['price'] ?>" required>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Cost Price (<?= CURRENCY ?>)</label>
                    <input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= $prod['cost'] ?>">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label">Stock</label>
                    <input type="number" min="0" name="stock" class="form-control" value="<?= $prod['stock'] ?>">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label">Unit</label>
                    <select name="unit" class="form-select">
                      <?php foreach(['pcs','kg','g','m','L','pair','box','set','roll'] as $u): ?>
                        <option value="<?= $u ?>" <?= $prod['unit']==$u?'selected':'' ?>><?= $u ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Optional description..."><?= htmlspecialchars($prod['description']) ?></textarea>
                  </div>

                  <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Product</button>
                    <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                  </div>

                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <?php if ($action === 'edit' && $prod['id']): ?>
          <div class="card mb-3">
            <div class="card-header"><i class="fas fa-palette me-2"></i>Product Colors <small class="text-muted">(for variants like bags)</small></div>
            <div class="card-body">
              <?php $colors = $conn->query("SELECT * FROM product_colors WHERE product_id={$prod['id']} ORDER BY color_name")->fetch_all(MYSQLI_ASSOC); ?>
              <?php if ($colors): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <?php foreach ($colors as $c): ?>
                    <span class="badge bg-secondary d-flex align-items-center gap-1" style="font-size:.85rem;padding:6px 10px">
                      <?= htmlspecialchars($c['color_name']) ?>
                      <a href="products.php?action=delete_color&id=<?= $c['id'] ?>"
                         class="text-white ms-1" style="text-decoration:none;line-height:1"
                         onclick="return confirm('Remove this color?')">&times;</a>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted small mb-3">No colors added yet.</p>
              <?php endif; ?>
              <form method="POST" action="products.php?action=add_color" class="d-flex gap-2">
                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                <input type="text" name="color_name" class="form-control form-control-sm"
                       placeholder="e.g. Red, Blue, Purple…" required style="max-width:180px">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add</button>
              </form>
            </div>
          </div>
          <?php endif; ?>
          <div class="card">
            <div class="card-header">Manage Categories</div>
            <div class="card-body">
              <form method="POST" action="categories.php" class="d-flex gap-2 mb-3">
                <input type="text" name="name" class="form-control" placeholder="New category name...">
                <button class="btn btn-success btn-sm px-3"><i class="fas fa-plus"></i></button>
              </form>
              <ul class="list-group list-group-flush">
                <?php foreach($categories as $cat): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <?= htmlspecialchars($cat['name']) ?>
                    <a href="categories.php?delete=<?= $cat['id'] ?>" class="text-danger small" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// ── Product List ──────────────────────────────────────────────────────────────────
$where = "WHERE p.active=1";
$search = sanitize($conn, $_GET['q'] ?? '');
$filterCat = (int)($_GET['cat'] ?? 0);
$filterStock = $_GET['filter'] ?? '';

if ($search)           $where .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
if ($filterCat)        $where .= " AND p.category_id=$filterCat";
if ($filterStock === 'low_stock') $where .= " AND p.stock <= 5";

$products = $conn->query("
    SELECT p.*, c.name as cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where
    ORDER BY p.created_at DESC
");
?>
<div class="container-fluid">

  <!-- Filter bar -->
  <form class="filter-bar d-flex flex-wrap gap-2 align-items-center mb-4" method="GET">
    <input type="text" name="q" class="form-control" style="max-width:220px" placeholder="&#128269; Search name or SKU..." value="<?= htmlspecialchars($search) ?>">
    <select name="cat" class="form-select" style="max-width:160px">
      <option value="">All Categories</option>
      <?php foreach($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $filterCat==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="filter" class="form-select" style="max-width:150px">
      <option value="">All Stock</option>
      <option value="low_stock" <?= $filterStock==='low_stock'?'selected':'' ?>>Low Stock (&le;5)</option>
    </select>
    <button class="btn btn-primary">Filter</button>
    <a href="products.php" class="btn btn-outline-secondary">Reset</a>
    <a href="products.php?action=add" class="btn btn-success ms-auto"><i class="fas fa-plus me-1"></i>Add Product</a>
  </form>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><i class="fas fa-box-open me-2 text-primary"></i>Products (<?= $products->num_rows ?>)</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>SKU</th>
              <th>Category</th>
              <th>Price</th>
              <th>Cost</th>
              <th>Stock</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($products->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No products found. <a href="products.php?action=add">Add one!</a></td></tr>
          <?php else: ?>
            <?php while($p = $products->fetch_assoc()): ?>
            <tr>
              <td class="text-muted"><?= $p['id'] ?></td>
              <td>
                <div class="fw-600"><?= htmlspecialchars($p['name']) ?></div>
                <?php if($p['description']): ?>
                  <div class="text-muted small"><?= htmlspecialchars(mb_substr($p['description'],0,50)) ?>...</div>
                <?php endif; ?>
              </td>
              <td><code class="small"><?= htmlspecialchars($p['sku'] ?: '—') ?></code></td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['cat_name'] ?? 'None') ?></span></td>
              <td class="fw-600"><?= currency($p['price']) ?></td>
              <td class="text-muted"><?= currency($p['cost']) ?></td>
              <td>
                <span class="<?= $p['stock']==0?'stock-low':($p['stock']<=5?'stock-warn':'stock-ok') ?>">
                  <?= $p['stock'] ?> <?= htmlspecialchars($p['unit']) ?>
                </span>
              </td>
              <td>
                <a href="products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>
                <a href="products.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this product?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
