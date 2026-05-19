<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin(); 

// Handle order status update before any output is sent to the browser
if (isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];

    // Only allow valid status values to prevent invalid data being saved
    $allowed = ['Pending','Preparing','Out for Delivery','Delivered'];
    if (in_array($status, $allowed)) {
        // Update order status using PDO prepared statement to prevent SQL injection
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $oid]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Order status updated.'];
    }
    header('Location: dashboard.php'); exit;
}

$pageTitle = 'Dashboard';
include 'header.php';

// Fetch count of new orders with Pending status for the stats card
$newOrders   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn();

// Fetch count of orders currently being prepared
$preparing   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Preparing'")->fetchColumn();

// Fetch count of orders currently out for delivery
$outDelivery = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Out for Delivery'")->fetchColumn();

// Calculate total revenue excluding Pending orders
// COALESCE returns 0 instead of NULL when no qualifying orders exist
$revenue     = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='Pending'")->fetchColumn();

// Fetch 10 most recent orders with customer info and item names
// GROUP_CONCAT combines multiple item names into one comma-separated string per order
$orders = $pdo->query("
    SELECT o.*, u.username, u.email,
    GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') as item_names
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN items i ON oi.item_id = i.id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Map order status values to CSS badge classes for colour coding in the table
$badgeMap = [
    'Pending'          => 'badge-pending',
    'Preparing'        => 'badge-preparing',
    'Out for Delivery' => 'badge-delivery',
    'Delivered'        => 'badge-delivered',
];
?>

<!-- STATS: displays 4 key metrics for the admin at a glance -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">New Orders</div>
        <div class="stat-value"><?= $newOrders ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Preparing</div>
        <div class="stat-value"><?= $preparing ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Out for Delivery</div>
        <div class="stat-value"><?= $outDelivery ?></div>
    </div>
    <!-- Accent card highlights total revenue with orange background -->
    <div class="stat-card accent">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">Rs <?= number_format($revenue, 0) ?></div>
    </div>
</div>

<!-- ORDERS TABLE: shows the 10 most recent incoming orders -->
<div class="card">
    <div class="card-header">
        <span>Incoming Orders</span>
        <a href="orders.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($orders)): ?>
                <!-- Show message if no orders exist yet -->
                <tr><td colspan="7" style="text-align:center;color:var(--gray);padding:2rem">No orders yet</td></tr>
            <?php else: ?>
                <?php foreach($orders as $order): ?>
                <tr>
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td>
                        <!-- Display customer username and email below -->
                        <div style="font-weight:600"><?= htmlspecialchars($order['username']) ?></div>
                        <div style="font-size:0.76rem;color:var(--gray)"><?= htmlspecialchars($order['email']) ?></div>
                    </td>
                    <!-- htmlspecialchars prevents XSS attacks on all user data output -->
                    <td style="max-width:200px;font-size:0.85rem"><?= htmlspecialchars($order['item_names']) ?></td>
                    <td style="font-weight:700;color:var(--orange)">Rs <?= number_format($order['total'], 0) ?></td>
                    <td style="font-size:0.85rem"><?= htmlspecialchars($order['address']) ?></td>
                    <!-- Apply badge colour class based on current order status -->
                    <td><span class="badge <?= $badgeMap[$order['status']] ?? 'badge-pending' ?>"><?= $order['status'] ?></span></td>
                    <td>
                        <!-- Inline form allows admin to update status without leaving the page -->
                        <form method="POST" style="display:flex;gap:0.4rem;align-items:center">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" class="form-control" style="padding:0.3rem;font-size:0.8rem;width:140px">
                                <?php foreach(['Pending','Preparing','Out for Delivery','Delivered'] as $s): ?>
                                <!-- Mark the current status as selected in the dropdown -->
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>