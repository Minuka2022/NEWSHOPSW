<?php
$pageTitle = 'Customers';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Delete ────────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $conn->query("DELETE FROM customers WHERE id=$id");
    flash('success', 'Customer deleted.');
    redirect(BASE_URL . '/customers.php');
}

// ── Save ──────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid     = (int)($_POST['id'] ?? 0);
    $name    = sanitize($conn, $_POST['name']    ?? '');
    $phone   = sanitize($conn, $_POST['phone']   ?? '');
    $email   = sanitize($conn, $_POST['email']   ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $notes   = sanitize($conn, $_POST['notes']   ?? '');

    if (!$name) { flash('danger', 'Name is required.'); redirect(BASE_URL . '/customers.php?action=' . ($cid ? 'edit&id='.$cid : 'add')); }

    if ($cid) {
        $stmt = $conn->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?");
        $stmt->bind_param('sssssi', $name, $phone, $email, $address, $notes, $cid);
        flash('success', 'Customer updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (name,phone,email,address,notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $name, $phone, $email, $address, $notes);
        flash('success', 'Customer added.');
    }
    $stmt->execute();
    redirect(BASE_URL . '/customers.php');
}

// ── Add / Edit Form ───────────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $cust = ['id'=>0,'name'=>'','phone'=>'','email'=>'','address'=>'','notes'=>''];
    if ($action === 'edit' && $id) {
        $r = $conn->query("SELECT * FROM customers WHERE id=$id LIMIT 1");
        if ($r->num_rows) $cust = $r->fetch_assoc();
    }
    // Order history for existing customer
    $orders = null;
    if ($cust['id']) {
        $orders = $conn->query("SELECT * FROM orders WHERE customer_id={$cust['id']} ORDER BY created_at DESC LIMIT 10");
    }
    ?>
    <div class="container-fluid">
      <div class="d-flex align-items-center mb-4 gap-3">
        <a href="customers.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        <h5 class="mb-0 fw-bold"><?= $action==='edit' ? 'Edit Customer' : 'Add Customer' ?></h5>
      </div>
      <div class="row">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body p-4">
              <form method="POST">
                <input type="hidden" name="id" value="<?= $cust['id'] ?>">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cust['name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($cust['phone']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cust['email']) ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Shipping Address</label>
                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($cust['address']) ?></textarea>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Internal notes..."><?= htmlspecialchars($cust['notes']) ?></textarea>
                  </div>
                  <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                    <a href="customers.php" class="btn btn-outline-secondary">Cancel</a>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php if ($orders): ?>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">Order History</div>
            <div class="card-body p-0">
              <?php if ($orders->num_rows === 0): ?>
                <div class="text-center text-muted py-4">No orders yet.</div>
              <?php else: ?>
                <table class="table mb-0">
                  <thead><tr><th>Order #</th><th>Status</th><th>Total</th><th>Date</th><th></th></tr></thead>
                  <tbody>
                  <?php while($ord = $orders->fetch_assoc()): ?>
                    <tr>
                      <td><a href="order_view.php?id=<?= $ord['id'] ?>"><?= $ord['order_number'] ?></a></td>
                      <td><?= statusBadge($ord['status']) ?></td>
                      <td><?= currency($ord['total']) ?></td>
                      <td class="text-muted small"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                      <td><a href="order_sticker.php?id=<?= $ord['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></a></td>
                    </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php require_once 'includes/footer.php'; exit;
}

// ── List ──────────────────────────────────────────────────────────────────────────
$search = sanitize($conn, $_GET['q'] ?? '');
$where  = $search ? "WHERE name LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%'" : '';

$customers = $conn->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM orders WHERE customer_id=c.id) as order_count,
           (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id=c.id AND status!='cancelled') as total_spent
    FROM customers c $where ORDER BY c.created_at DESC
");
?>
<div class="container-fluid">
  <div class="filter-bar d-flex gap-2 align-items-center mb-4">
    <form class="d-flex gap-2 flex-grow-1" method="GET">
      <input type="text" name="q" class="form-control" style="max-width:300px" placeholder="&#128269; Search name, phone, email..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-primary">Search</button>
      <?php if($search): ?><a href="customers.php" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
    </form>
    <a href="customers.php?action=add" class="btn btn-success ms-auto"><i class="fas fa-user-plus me-1"></i>Add Customer</a>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-users me-2 text-primary"></i>Customers (<?= $customers->num_rows ?>)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Since</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ($customers->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No customers found. <a href="customers.php?action=add">Add one!</a></td></tr>
          <?php else: ?>
            <?php while($c = $customers->fetch_assoc()): ?>
            <tr>
              <td class="text-muted"><?= $c['id'] ?></td>
              <td>
                <a href="customers.php?action=edit&id=<?= $c['id'] ?>" class="fw-600 text-dark"><?= htmlspecialchars($c['name']) ?></a>
                <?php if($c['address']): ?>
                  <div class="text-muted small"><?= htmlspecialchars(mb_substr($c['address'],0,40)) ?>...</div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
              <td class="small"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
              <td><span class="badge bg-primary"><?= $c['order_count'] ?></span></td>
              <td class="fw-600"><?= currency($c['total_spent']) ?></td>
              <td class="text-muted small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
              <td>
                <a href="customers.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>
                <a href="orders.php?action=new&customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="New Order"><i class="fas fa-cart-plus"></i></a>
                <a href="customers.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer?')"><i class="fas fa-trash"></i></a>
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
