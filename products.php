<?php
$pageTitle = 'Products';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Add color (with stock) ────────────────────────────────────────────────────────
if ($action === 'add_color' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)($_POST['product_id'] ?? 0);
    $color = sanitize($conn, trim($_POST['color_name'] ?? ''));
    $stock = max(0, (int)($_POST['color_stock'] ?? 0));
    if ($pid && $color) {
        $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, stock) VALUES (?,?,?)");
        $stmt->bind_param('isi', $pid, $color, $stock);
        $stmt->execute();
        flash('success', "Color \"$color\" added (stock: $stock).");
    }
    redirect(BASE_URL . "/products.php?action=edit&id=$pid");
}

// ── Update color stock ────────────────────────────────────────────────────────────
if ($action === 'update_color_stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $color_id = (int)($_POST['color_id'] ?? 0);
    $stock    = max(0, (int)($_POST['stock'] ?? 0));
    if ($color_id) {
        $stmt = $conn->prepare("UPDATE product_colors SET stock=? WHERE id=?");
        $stmt->bind_param('ii', $stock, $color_id);
        $stmt->execute();
        $row = $conn->query("SELECT product_id FROM product_colors WHERE id=$color_id LIMIT 1")->fetch_assoc();
        flash('success', 'Stock updated.');
        redirect(BASE_URL . "/products.php?action=edit&id=" . ($row['product_id'] ?? ''));
    }
    redirect(BASE_URL . '/products.php');
}

// ── Delete color ──────────────────────────────────────────────────────────────────
if ($action === 'delete_color' && $id) {
    $row = $conn->query("SELECT product_id FROM product_colors WHERE id=$id LIMIT 1")->fetch_assoc();
    $conn->query("DELETE FROM product_colors WHERE id=$id");
    flash('success', 'Color removed.');
    redirect(BASE_URL . "/products.php?action=edit&id=" . ($row['product_id'] ?? ''));
}

// ── Delete product ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("UPDATE products SET active=0 WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Product removed.');
    redirect(BASE_URL . '/products.php');
}

// ── Save product (Add/Edit) ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid    = (int)($_POST['id'] ?? 0);
    $name   = sanitize($conn, $_POST['name'] ?? '');
    $cat_id = (int)($_POST['category_id'] ?? 0) ?: 'NULL';
    $sku    = sanitize($conn, $_POST['sku']  ?? '');
    $desc   = sanitize($conn, $_POST['description'] ?? '');
    $price  = (float)($_POST['price'] ?? 0);
    $cost   = (float)($_POST['cost']  ?? 0);
    $stock  = (int)($_POST['stock']   ?? 0);
    $unit   = sanitize($conn, $_POST['unit'] ?? 'pcs');

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

// ── Fetch categories ──────────────────────────────────────────────────────────────
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ── Add / Edit Form ───────────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $prod = ['id'=>0,'category_id'=>'','name'=>'','sku'=>'','description'=>'','price'=>'','cost'=>'','stock'=>0,'unit'=>'pcs'];
    if ($action === 'edit' && $id) {
        $r = $conn->query("SELECT * FROM products WHERE id=$id LIMIT 1");
        if ($r->num_rows) $prod = $r->fetch_assoc();
    }
    ?>
    <div class="container-fluid">
      <div class="d-flex align-items-center mb-4 gap-3">
        <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        <h5 class="mb-0 fw-bold"><?= $action==='edit' ? 'Edit Product' : 'Add Product' ?></h5>
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
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($prod['name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                      <option value="">— None —</option>
                      <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $prod['category_id']==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">SKU / Barcode</label>
                    <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($prod['sku']) ?>">
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
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($prod['description']) ?></textarea>
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
            <div class="card-header"><i class="fas fa-palette me-2"></i>Color Variants &amp; Stock</div>
            <div class="card-body p-0">
              <?php $colors = $conn->query("SELECT * FROM product_colors WHERE product_id={$prod['id']} ORDER BY color_name")->fetch_all(MYSQLI_ASSOC); ?>
              <?php if ($colors): ?>
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Color</th><th style="width:90px">Stock</th><th style="width:80px"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($colors as $c): ?>
                  <tr>
                    <td class="align-middle">
                      <span class="badge" style="background:#6c757d;font-size:.8rem;padding:5px 8px"><?= htmlspecialchars($c['color_name']) ?></span>
                    </td>
                    <td>
                      <form method="POST" action="products.php?action=update_color_stock" class="d-flex gap-1">
                        <input type="hidden" name="color_id" value="<?= $c['id'] ?>">
                        <input type="number" name="stock" value="<?= (int)$c['stock'] ?>" min="0"
                               class="form-control form-control-sm <?= $c['stock']==0?'border-danger':($c['stock']<=3?'border-warning':'') ?>"
                               style="width:65px">
                        <button type="submit" class="btn btn-sm btn-outline-primary px-2" title="Save stock"><i class="fas fa-save"></i></button>
                      </form>
                    </td>
                    <td class="align-middle">
                      <a href="products.php?action=delete_color&id=<?= $c['id'] ?>"
                         class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Remove <?= htmlspecialchars($c['color_name']) ?>?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php else: ?>
                <div class="p-3 text-muted small">No color variants yet. Add one below.</div>
              <?php endif; ?>
              <div class="p-3 border-top">
                <form method="POST" action="products.php?action=add_color" class="d-flex gap-2 align-items-end">
                  <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                  <div class="flex-grow-1">
                    <label class="form-label small mb-1">Color Name</label>
                    <input type="text" name="color_name" class="form-control form-control-sm" placeholder="e.g. Brown, Red…" required>
                  </div>
                  <div style="width:80px">
                    <label class="form-label small mb-1">Stock</label>
                    <input type="number" name="color_stock" class="form-control form-control-sm" value="0" min="0">
                  </div>
                  <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add</button>
                </form>
              </div>
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
$search      = sanitize($conn, $_GET['q']      ?? '');
$filterCat   = (int)($_GET['cat']   ?? 0);
$filterStock = $_GET['filter'] ?? '';
$filterColor = sanitize($conn, $_GET['color']  ?? '');

$where = "WHERE p.active=1";
if ($search)      $where .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
if ($filterCat)   $where .= " AND p.category_id=$filterCat";
if ($filterColor) $where .= " AND p.id IN (SELECT product_id FROM product_colors WHERE color_name='$filterColor' AND stock>0)";
if ($filterStock === 'low_stock') $where .= " AND p.stock <= 5 AND p.id NOT IN (SELECT product_id FROM product_colors WHERE stock>0)";

$products = $conn->query("
    SELECT p.*, c.name as cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where
    ORDER BY p.created_at DESC
");

// All distinct colors for the filter dropdown
$allColors = $conn->query("SELECT DISTINCT color_name FROM product_colors ORDER BY color_name")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">

  <form class="filter-bar d-flex flex-wrap gap-2 align-items-center mb-4" method="GET">
    <input type="text" name="q" class="form-control" style="max-width:200px" placeholder="&#128269; Name or SKU..." value="<?= htmlspecialchars($search) ?>">
    <select name="cat" class="form-select" style="max-width:150px">
      <option value="">All Categories</option>
      <?php foreach($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $filterCat==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="color" class="form-select" style="max-width:150px">
      <option value="">All Colors</option>
      <?php foreach($allColors as $col): ?>
        <option value="<?= htmlspecialchars($col['color_name']) ?>" <?= $filterColor===$col['color_name']?'selected':'' ?>>
          <?= htmlspecialchars($col['color_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="filter" class="form-select" style="max-width:150px">
      <option value="">All Stock</option>
      <option value="low_stock" <?= $filterStock==='low_stock'?'selected':'' ?>>Low Stock</option>
    </select>
    <button class="btn btn-primary">Filter</button>
    <a href="products.php" class="btn btn-outline-secondary">Reset</a>
    <a href="products.php?action=add" class="btn btn-success ms-auto"><i class="fas fa-plus me-1"></i>Add Product</a>
  </form>

  <?php if ($filterColor): ?>
  <div class="alert alert-info py-2 mb-3">
    <i class="fas fa-palette me-2"></i>Showing products with <strong><?= htmlspecialchars($filterColor) ?></strong> color in stock.
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><i class="fas fa-box-open me-2 text-primary"></i>Products (<?= $products->num_rows ?>)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr><th>#</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock / Colors</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if ($products->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center py-5 text-muted">No products found. <a href="products.php?action=add">Add one!</a></td></tr>
          <?php else: ?>
            <?php while($p = $products->fetch_assoc()):
              $pColors = $conn->query("SELECT color_name, stock FROM product_colors WHERE product_id={$p['id']} ORDER BY color_name")->fetch_all(MYSQLI_ASSOC);
            ?>
            <tr>
              <td class="text-muted small"><?= $p['id'] ?></td>
              <td>
                <div class="fw-600"><?= htmlspecialchars($p['name']) ?></div>
                <?php if($p['description']): ?>
                  <div class="text-muted small"><?= htmlspecialchars(mb_substr($p['description'],0,45)) ?>…</div>
                <?php endif; ?>
              </td>
              <td><code class="small"><?= htmlspecialchars($p['sku'] ?: '—') ?></code></td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['cat_name'] ?? 'None') ?></span></td>
              <td class="fw-600"><?= currency($p['price']) ?></td>
              <td>
                <?php if ($pColors): ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($pColors as $pc):
                      $bg = $pc['stock'] == 0 ? 'bg-danger' : ($pc['stock'] <= 3 ? 'bg-warning text-dark' : 'bg-success');
                    ?>
                      <span class="badge <?= $bg ?>" title="<?= htmlspecialchars($pc['color_name']) ?>: <?= $pc['stock'] ?> in stock">
                        <?= htmlspecialchars($pc['color_name']) ?> (<?= $pc['stock'] ?>)
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="<?= $p['stock']==0?'stock-low':($p['stock']<=5?'stock-warn':'stock-ok') ?>">
                    <?= $p['stock'] ?> <?= htmlspecialchars($p['unit']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <a href="products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>
                <a href="products.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove?')"><i class="fas fa-trash"></i></a>
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
