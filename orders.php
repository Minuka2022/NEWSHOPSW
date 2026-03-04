<?php
// Bootstrap without HTML output
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'list';

// ── AJAX: product search ── (must be before ANY HTML output) ──────────────────────
if ($action === 'product_search') {
    header('Content-Type: application/json');
    $q = sanitize($conn, $_GET['q'] ?? '');
    try {
        // Include products that have stock OR have any color with stock > 0
        $r = $conn->query("
            SELECT p.id, p.name, p.price, p.stock, p.unit,
                   GROUP_CONCAT(CONCAT(pc.color_name,':',pc.stock) ORDER BY pc.color_name SEPARATOR '||') AS colors_raw
            FROM products p
            LEFT JOIN product_colors pc ON pc.product_id = p.id AND pc.stock > 0
            WHERE p.active=1
              AND (p.name LIKE '%$q%' OR p.sku LIKE '%$q%')
              AND (p.stock > 0 OR EXISTS (SELECT 1 FROM product_colors x WHERE x.product_id=p.id AND x.stock>0))
            GROUP BY p.id LIMIT 10
        ");
        $prods = [];
        while ($row = $r->fetch_assoc()) {
            $row['colors'] = [];
            if ($row['colors_raw']) {
                foreach (explode('||', $row['colors_raw']) as $cs) {
                    [$cname, $cstock] = explode(':', $cs, 2);
                    $row['colors'][] = ['name' => $cname, 'stock' => (int)$cstock];
                }
            }
            unset($row['colors_raw']);
            $prods[] = $row;
        }
    } catch (Exception $e) {
        $r = $conn->query("SELECT id,name,price,stock,unit FROM products WHERE active=1 AND stock>0 AND (name LIKE '%$q%' OR sku LIKE '%$q%') LIMIT 10");
        $prods = [];
        while ($row = $r->fetch_assoc()) { $row['colors'] = []; $prods[] = $row; }
    }
    echo json_encode($prods);
    exit;
}

// ── Status update ─────────────────────────────────────────────────────────────────
if ($action === 'update_status' && isset($_GET['id'], $_GET['status'])) {
    $oid    = (int)$_GET['id'];
    $status = sanitize($conn, $_GET['status']);
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        $conn->query("UPDATE orders SET status='$status' WHERE id=$oid");
        flash('success', 'Order status updated.');
    }
    redirect(BASE_URL . '/orders.php');
}

// ── Save new order ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new') {
    $cust_id     = (int)($_POST['customer_id'] ?? 0) ?: 'NULL';
    $ship_addr   = sanitize($conn, $_POST['shipping_address'] ?? '');
    $notes       = sanitize($conn, $_POST['notes'] ?? '');
    $discount    = (float)($_POST['discount'] ?? 0);
    $prod_ids    = $_POST['product_id']  ?? [];
    $quantities  = $_POST['quantity']    ?? [];
    $prices      = $_POST['unit_price']  ?? [];
    $item_colors = $_POST['item_color']  ?? [];

    if (empty($prod_ids) || count(array_filter($quantities)) === 0) {
        flash('danger', 'Please add at least one item to the order.');
        redirect(BASE_URL . '/orders.php?action=new');
    }

    $subtotal = 0;
    $items    = [];
    foreach ($prod_ids as $idx => $pid) {
        $pid   = (int)$pid;
        $qty   = (int)($quantities[$idx]   ?? 0);
        $up    = (float)($prices[$idx]     ?? 0);
        $color = sanitize($conn, $item_colors[$idx] ?? '');
        if ($pid && $qty > 0) {
            $tp = $qty * $up;
            $subtotal += $tp;
            $items[] = [$pid, $qty, $up, $tp, $color];
        }
    }

    $total     = max(0, $subtotal - $discount);
    $order_num = generateOrderNumber($conn);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id,order_number,subtotal,discount,total,notes,shipping_address) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isdddss', $cust_id, $order_num, $subtotal, $discount, $total, $notes, $ship_addr);
        $stmt->execute();
        $order_id = $conn->insert_id;

        $stmt2  = $conn->prepare("INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,total_price,color) VALUES (?,?,(SELECT name FROM products WHERE id=?),?,?,?,?)");
        $stmt3  = $conn->prepare("UPDATE products       SET stock=stock-? WHERE id=?          AND stock>=?");
        $stmt3c = $conn->prepare("UPDATE product_colors SET stock=stock-? WHERE product_id=? AND color_name=? AND stock>=?");

        foreach ($items as [$pid, $qty, $up, $tp, $color]) {
            $stmt2->bind_param('iiiidds', $order_id, $pid, $pid, $qty, $up, $tp, $color);
            $stmt2->execute();
            if ($color) {
                // Decrement per-color stock
                $stmt3c->bind_param('iisi', $qty, $pid, $color, $qty);
                $stmt3c->execute();
            } else {
                // Decrement main product stock
                $stmt3->bind_param('iii', $qty, $pid, $qty);
                $stmt3->execute();
            }
        }

        $conn->commit();
        flash('success', "Order $order_num created successfully!");
        redirect(BASE_URL . '/order_view.php?id=' . $order_id);
    } catch (Exception $e) {
        $conn->rollback();
        flash('danger', 'Error creating order: ' . $e->getMessage());
        redirect(BASE_URL . '/orders.php?action=new');
    }
}

// ── From here on we need HTML ─────────────────────────────────────────────────────
$pageTitle = 'Orders';
require_once 'includes/header.php';

// ── New Order Form ────────────────────────────────────────────────────────────────
if ($action === 'new') {
    $customers = $conn->query("SELECT id,name,phone,address FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $selCust   = (int)($_GET['customer_id'] ?? 0);
    $custData  = [];
    if ($selCust) {
        $r = $conn->query("SELECT * FROM customers WHERE id=$selCust LIMIT 1");
        if ($r->num_rows) $custData = $r->fetch_assoc();
    }
    ?>
    <div class="container-fluid">
      <div class="d-flex align-items-center mb-4 gap-3">
        <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        <h5 class="mb-0 fw-bold">New Order</h5>
      </div>
      <form method="POST" action="orders.php?action=new" id="orderForm">
        <div class="row g-3">

          <!-- Left: Customer & Details -->
          <div class="col-lg-4">
            <div class="card mb-3">
              <div class="card-header"><i class="fas fa-user me-2"></i>Customer</div>
              <div class="card-body">
                <label class="form-label">Select Customer</label>
                <select name="customer_id" id="custSelect" class="form-select mb-2" onchange="fillAddress(this)">
                  <option value="">— Walk-in / No customer —</option>
                  <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"
                            data-address="<?= htmlspecialchars($c['address']) ?>"
                            data-phone="<?= htmlspecialchars($c['phone']) ?>"
                            <?= $selCust==$c['id']?'selected':'' ?>>
                      <?= htmlspecialchars($c['name']) ?> <?= $c['phone']?'('.$c['phone'].')':'' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <a href="customers.php?action=add" class="small text-primary">+ Add new customer</a>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-header"><i class="fas fa-truck me-2"></i>Shipping</div>
              <div class="card-body">
                <label class="form-label">Shipping Address</label>
                <textarea name="shipping_address" id="shipAddr" class="form-control" rows="3"><?= htmlspecialchars($custData['address'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="card">
              <div class="card-header"><i class="fas fa-receipt me-2"></i>Summary</div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span>Subtotal</span>
                  <strong id="summSubtotal"><?= CURRENCY ?>0.00</strong>
                </div>
                <div class="mb-2">
                  <label class="form-label">Discount (<?= CURRENCY ?>)</label>
                  <input type="number" step="0.01" min="0" name="discount" id="discountInput" class="form-control" value="0" oninput="calcTotal()">
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5">
                  <span>Total</span>
                  <span id="summTotal" class="text-primary"><?= CURRENCY ?>0.00</span>
                </div>
                <div class="mt-3">
                  <label class="form-label">Order Notes</label>
                  <textarea name="notes" class="form-control" rows="2" placeholder="Optional..."></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100 mt-3 py-2">
                  <i class="fas fa-check-circle me-2"></i>Create Order
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Items -->
          <div class="col-lg-8">
            <div class="card">
              <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fas fa-list me-2"></i>Order Items</span>
                <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">
                  <i class="fas fa-plus me-1"></i>Add Item
                </button>
              </div>
              <div class="card-body">
                <div class="mb-3 position-relative">
                  <input type="text" id="productSearch" class="form-control" placeholder="&#128269; Type product name to search and add...">
                  <div id="searchDropdown" class="position-absolute w-100 bg-white border rounded shadow-sm" style="top:100%;z-index:999;display:none;max-height:220px;overflow-y:auto"></div>
                </div>
                <div id="orderItems">
                  <div class="text-center text-muted py-5" id="emptyMsg">
                    <i class="fas fa-box-open fa-2x mb-2"></i><br>Search products above to add items
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </form>
    </div>

    <?php
    $curr = htmlspecialchars(CURRENCY, ENT_QUOTES);
    ob_start(); ?>
<script>
var CURRENCY = "<?= $curr ?>";

function fillAddress(sel) {
    var opt = sel.options[sel.selectedIndex];
    document.getElementById("shipAddr").value = opt.getAttribute("data-address") || "";
}

function addItem(id, name, price, colors) {
    id     = id    || 0;
    name   = name  || "";
    price  = parseFloat(price) || 0;
    colors = colors || [];

    var div = document.createElement("div");
    div.className = "order-item-row d-flex align-items-center gap-2 mb-1";

    var hiddenId = document.createElement("input");
    hiddenId.type = "hidden"; hiddenId.name = "product_id[]"; hiddenId.value = id;

    var nameWrap  = document.createElement("div"); nameWrap.className = "flex-grow-1";
    var nameInput = document.createElement("input");
    nameInput.type = "text"; nameInput.className = "form-control form-control-sm";
    nameInput.value = name; nameInput.readOnly = true;
    nameWrap.appendChild(nameInput);

    var qtyWrap  = document.createElement("div"); qtyWrap.style.width = "80px";
    var qtyInput = document.createElement("input");
    qtyInput.type = "number"; qtyInput.name = "quantity[]";
    qtyInput.className = "form-control form-control-sm qty";
    qtyInput.min = "1"; qtyInput.value = "1"; qtyInput.required = true;
    qtyInput.addEventListener("input", function(){ updateRow(this); });
    qtyWrap.appendChild(qtyInput);

    var colorWrap = document.createElement("div"); colorWrap.style.width = "120px";
    if (colors.length > 0) {
        var colorSel = document.createElement("select");
        colorSel.name = "item_color[]"; colorSel.className = "form-select form-select-sm";
        var blank = document.createElement("option"); blank.value = ""; blank.textContent = "Color\u2026";
        colorSel.appendChild(blank);
        colors.forEach(function(c) {
            var opt = document.createElement("option");
            opt.value = c.name;
            opt.textContent = c.name + " (" + c.stock + ")";
            colorSel.appendChild(opt);
        });
        colorWrap.appendChild(colorSel);
    } else {
        var hc = document.createElement("input");
        hc.type = "hidden"; hc.name = "item_color[]"; hc.value = "";
        colorWrap.appendChild(hc);
    }

    var priceWrap = document.createElement("div"); priceWrap.style.width = "110px";
    var ig        = document.createElement("div"); ig.className = "input-group input-group-sm";
    var igSpan    = document.createElement("span"); igSpan.className = "input-group-text"; igSpan.textContent = CURRENCY;
    var upInput   = document.createElement("input");
    upInput.type = "number"; upInput.step = "0.01"; upInput.min = "0";
    upInput.name = "unit_price[]"; upInput.className = "form-control up";
    upInput.value = price.toFixed(2); upInput.required = true;
    upInput.addEventListener("input", function(){ updateRow(this); });
    ig.appendChild(igSpan); ig.appendChild(upInput);
    priceWrap.appendChild(ig);

    var totalDiv = document.createElement("div");
    totalDiv.style.width = "90px"; totalDiv.className = "text-end fw-600 rowtotal";
    totalDiv.textContent = CURRENCY + price.toFixed(2);

    var delBtn = document.createElement("button");
    delBtn.type = "button"; delBtn.className = "btn btn-sm btn-outline-danger";
    delBtn.innerHTML = "&times;";
    delBtn.addEventListener("click", function(){ removeItem(this); });

    div.appendChild(hiddenId); div.appendChild(nameWrap);
    div.appendChild(qtyWrap);  div.appendChild(colorWrap);
    div.appendChild(priceWrap); div.appendChild(totalDiv); div.appendChild(delBtn);

    document.getElementById("orderItems").appendChild(div);
    document.getElementById("emptyMsg").style.display = "none";
    calcTotal();
}

function removeItem(btn) {
    btn.closest(".order-item-row").remove();
    if (document.querySelectorAll(".order-item-row").length === 0)
        document.getElementById("emptyMsg").style.display = "";
    calcTotal();
}

function updateRow(inp) {
    var row = inp.closest(".order-item-row");
    var qty = parseFloat(row.querySelector(".qty").value) || 0;
    var up  = parseFloat(row.querySelector(".up").value)  || 0;
    row.querySelector(".rowtotal").textContent = CURRENCY + (qty * up).toFixed(2);
    calcTotal();
}

function calcTotal() {
    var sub = 0;
    document.querySelectorAll(".order-item-row").forEach(function(row) {
        sub += (parseFloat(row.querySelector(".qty").value)||0) * (parseFloat(row.querySelector(".up").value)||0);
    });
    var disc  = parseFloat(document.getElementById("discountInput").value) || 0;
    var total = Math.max(0, sub - disc);
    document.getElementById("summSubtotal").textContent = CURRENCY + sub.toFixed(2);
    document.getElementById("summTotal").textContent    = CURRENCY + total.toFixed(2);
}

var searchTimer;
document.getElementById("productSearch").addEventListener("input", function() {
    clearTimeout(searchTimer);
    var q  = this.value.trim();
    var dd = document.getElementById("searchDropdown");
    if (!q) { dd.style.display = "none"; return; }
    searchTimer = setTimeout(function() {
        fetch("orders.php?action=product_search&q=" + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .catch(function() { return []; })
            .then(function(data) {
                dd.innerHTML = "";
                if (!data.length) {
                    dd.innerHTML = "<div class='p-3 text-muted small'>No products found</div>";
                    dd.style.display = "block";
                    return;
                }
                data.forEach(function(p) {
                    var el = document.createElement("div");
                    el.className = "px-3 py-2 small border-bottom";
                    el.style.cursor = "pointer";
                    el.dataset.id     = p.id;
                    el.dataset.price  = p.price;
                    el.dataset.name   = p.name;
                    el.dataset.colors = JSON.stringify(p.colors || []);
                    var colorBadge = p.colors && p.colors.length
                        ? " <span class='badge bg-info text-dark'>" + p.colors.map(function(c){return c.name+"("+c.stock+")"}).join(", ") + "</span>" : "";
                    el.innerHTML = "<b>" + p.name + "</b> &mdash; " + CURRENCY +
                                   parseFloat(p.price).toFixed(2) +
                                   " <span class='text-muted'>(Stock: " + p.stock + " " + p.unit + ")</span>" +
                                   colorBadge;
                    el.addEventListener("mousedown", function() {
                        selectProduct(this.dataset.id, this.dataset.name,
                                      parseFloat(this.dataset.price),
                                      JSON.parse(this.dataset.colors || "[]"));
                    });
                    dd.appendChild(el);
                });
                dd.style.display = "block";
            });
    }, 250);
});

function selectProduct(id, name, price, colors) {
    addItem(id, name, price, colors || []);
    document.getElementById("productSearch").value = "";
    document.getElementById("searchDropdown").style.display = "none";
}

document.addEventListener("click", function(e) {
    if (!e.target.closest("#productSearch"))
        document.getElementById("searchDropdown").style.display = "none";
});

var custSel = document.getElementById("custSelect");
if (custSel && custSel.value) fillAddress(custSel);
</script>
    <?php
    $extraJS = ob_get_clean();
    require_once 'includes/footer.php';
    exit;
}

// ── Order List ────────────────────────────────────────────────────────────────────
$statusFilter = sanitize($conn, $_GET['status'] ?? '');
$search       = sanitize($conn, $_GET['q']      ?? '');

$where = "WHERE 1=1";
if ($statusFilter) $where .= " AND o.status='$statusFilter'";
if ($search)       $where .= " AND (o.order_number LIKE '%$search%' OR c.name LIKE '%$search%')";

$orders = $conn->query("
    SELECT o.*, c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    $where
    ORDER BY o.created_at DESC
");

$statusCounts = [];
foreach (['pending','processing','shipped','delivered','cancelled'] as $st) {
    $r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='$st'")->fetch_assoc();
    $statusCounts[$st] = $r['c'];
}
?>
<div class="container-fluid">

  <div class="d-flex gap-2 flex-wrap mb-3">
    <a href="orders.php" class="btn btn-sm <?= !$statusFilter?'btn-dark':'btn-outline-secondary' ?>">All</a>
    <?php foreach($statusCounts as $st => $cnt): ?>
      <?php $colors = ['pending'=>'warning','processing'=>'primary','shipped'=>'info','delivered'=>'success','cancelled'=>'danger']; ?>
      <a href="orders.php?status=<?= $st ?>" class="btn btn-sm btn-outline-<?= $colors[$st] ?> <?= $statusFilter===$st?'active':'' ?>">
        <?= ucfirst($st) ?> <span class="badge bg-<?= $colors[$st] ?> <?= $st==='pending'?'text-dark':'' ?>"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>
    <form class="d-flex gap-2 ms-auto" method="GET">
      <?php if($statusFilter): ?><input type="hidden" name="status" value="<?= $statusFilter ?>"><?php endif; ?>
      <input type="text" name="q" class="form-control form-control-sm" style="width:200px" placeholder="Search order # or customer..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-primary btn-sm">Go</button>
    </form>
    <a href="orders.php?action=new" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>New Order</a>
    <a href="print_stickers.php" class="btn btn-warning btn-sm" target="_blank"><i class="fas fa-tags me-1"></i>Print Pending Labels</a>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-shopping-cart me-2 text-primary"></i>Orders (<?= $orders->num_rows ?>)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr><th>Order #</th><th>Customer</th><th>Items</th><th>Status</th><th>Total</th><th>Date</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if ($orders->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center py-5 text-muted">No orders found. <a href="orders.php?action=new">Create one!</a></td></tr>
          <?php else: ?>
            <?php while($ord = $orders->fetch_assoc()):
              $itemCount = $conn->query("SELECT COUNT(*) as c FROM order_items WHERE order_id={$ord['id']}")->fetch_assoc()['c'];
            ?>
            <tr>
              <td><a href="order_view.php?id=<?= $ord['id'] ?>" class="fw-bold text-primary"><?= htmlspecialchars($ord['order_number']) ?></a></td>
              <td><?= htmlspecialchars($ord['customer_name'] ?? 'Walk-in') ?></td>
              <td><span class="badge bg-light text-dark border"><?= $itemCount ?> items</span></td>
              <td>
                <div class="dropdown">
                  <button class="btn btn-sm dropdown-toggle border-0 p-0" data-bs-toggle="dropdown"><?= statusBadge($ord['status']) ?></button>
                  <ul class="dropdown-menu dropdown-menu-sm">
                    <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $st): ?>
                      <?php if($st !== $ord['status']): ?>
                        <li><a class="dropdown-item small" href="orders.php?action=update_status&id=<?= $ord['id'] ?>&status=<?= $st ?>"><?= ucfirst($st) ?></a></li>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </td>
              <td class="fw-bold"><?= currency($ord['total']) ?></td>
              <td class="text-muted small"><?= date('d M Y H:i', strtotime($ord['created_at'])) ?></td>
              <td>
                <a href="order_view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View"><i class="fas fa-eye"></i></a>
                <a href="order_sticker.php?id=<?= $ord['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Print Sticker"><i class="fas fa-print"></i></a>
                <a href="orders.php?action=update_status&id=<?= $ord['id'] ?>&status=cancelled" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Cancel this order?')" title="Cancel"><i class="fas fa-times"></i></a>
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
