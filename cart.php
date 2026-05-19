<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function fetchItem(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, name, price FROM items WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
function isAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}
function cartCount(): int
{
    return (int) array_sum(array_column($_SESSION['cart'], 'qty'));
}
function redirect(string $path): never
{
    header("Location: $path");
    exit;
}
function verifyCsrf(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request.');
    }
}
if (isset($_GET['add'])) {
    $id   = (int) $_GET['add'];
    $item = fetchItem($pdo, $id);

    if ($item) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty']++;
        } else {
            $_SESSION['cart'][$id] = [
                'id'    => $item['id'],
                'name'  => $item['name'],
                'price' => $item['price'],
                'qty'   => 1,
            ];
        }
    }
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool) $item, 'cartCount' => cartCount()]);
        exit;
    }

    redirect('/foodbyte/cart.php');
}


if (isset($_POST['update_qty'])) {
    verifyCsrf();

    $id  = (int) $_POST['item_id'];
    $qty = (int) $_POST['qty'];

    if (isset($_SESSION['cart'][$id])) {
        if ($qty > 0) {
            $_SESSION['cart'][$id]['qty'] = $qty;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }

    redirect('/foodbyte/cart.php');
}


if (isset($_GET['remove'])) {
    $id = (int) $_GET['remove'];
    unset($_SESSION['cart'][$id]);
    redirect('/foodbyte/cart.php');
}


$cart     = $_SESSION['cart'];
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
$delivery = $subtotal > 0 ? 100 : 0;
$total    = $subtotal + $delivery;

$pageTitle = 'My Cart';
include 'includes/header.php';
?>

<style>
.cart-container {
    max-width: 1000px; margin: 2.5rem auto; padding: 0 2rem;
    display: grid; grid-template-columns: 1fr 320px; gap: 2rem;
    align-items: start;
}
.cart-box, .summary-box {
    background: var(--warm-white);
    border-radius: 16px;
    border: 1px solid var(--border);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.box-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem; font-weight: 800;
}
.cart-table { width: 100%; border-collapse: collapse; }
.cart-table th {
    text-align: left; padding: 0.8rem 1.5rem;
    font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--gray); background: var(--cream);
    border-bottom: 1px solid var(--border);
}
.cart-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--light-gray);
    vertical-align: middle;
}
.cart-table tr:last-child td { border-bottom: none; }
.item-name { font-weight: 600; font-size: 0.95rem; }
.qty-control { display: flex; align-items: center; }
.qty-btn {
    width: 30px; height: 30px;
    border: 1.5px solid var(--border);
    background: var(--cream);
    border-radius: 7px;
    cursor: pointer; font-size: 1rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.qty-btn:hover { border-color: var(--orange); color: var(--orange); }
.qty-num {
    width: 40px; text-align: center;
    font-weight: 700; font-size: 0.95rem;
    border: 1.5px solid var(--border);
    border-left: none; border-right: none;
    padding: 4px 0;
    background: white;
}
.subtotal { font-weight: 700; color: var(--orange); }
.delete-btn {
    background: none; border: none;
    color: #E53935; cursor: pointer;
    font-size: 1.1rem; padding: 4px;
    border-radius: 6px; transition: all 0.2s;
}
.delete-btn:hover { background: #FFEBEE; }
.summary-rows { padding: 1.2rem 1.5rem; }
.summary-row {
    display: flex; justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.92rem;
    border-bottom: 1px solid var(--light-gray);
}
.summary-row:last-child { border-bottom: none; }
.summary-row.total { font-weight: 800; font-size: 1rem; color: var(--charcoal); }
.checkout-btn {
    display: block; text-align: center; text-decoration: none;
    width: calc(100% - 3rem); margin: 1.2rem 1.5rem;
    background: var(--orange); color: #fff;
    border: none; border-radius: 10px;
    padding: 0.85rem;
    font-size: 0.95rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    font-family: 'DM Sans', sans-serif;
}
.checkout-btn:hover { background: var(--orange-dark); }
.empty-cart { text-align: center; padding: 4rem 2rem; color: var(--gray); }
.empty-cart .emoji { font-size: 4rem; margin-bottom: 1rem; }
.page-title {
    padding: 2rem 3rem 0;
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem; font-weight: 800;
}
</style>

<h1 class="page-title">My Cart</h1>

<div class="cart-container">

    <!-- CART ITEMS -->
    <div class="cart-box">
        <div class="box-header">Items</div>

        <?php if (empty($cart)): ?>
            <div class="empty-cart">
                <div class="emoji">🛒</div>
                <p>Your cart is empty.</p>
                <a href="/foodbyte/menu.php" class="btn btn-primary" style="margin-top:1rem">Browse Menu</a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Item Detail</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $id => $item): ?>
                    <tr>
                        <td>
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div style="font-size:0.8rem;color:var(--gray)">
                                Rs <?= number_format($item['price'], 0) ?> each
                            </div>
                        </td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf"       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="item_id"    value="<?= (int) $id ?>">
                                <input type="hidden" name="update_qty" value="1">
                                <div class="qty-control">
                                    <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" class="qty-btn">−</button>
                                    <div class="qty-num"><?= (int) $item['qty'] ?></div>
                                    <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" class="qty-btn">+</button>
                                </div>
                            </form>
                        </td>
                        <td class="subtotal">Rs <?= number_format($item['price'] * $item['qty'], 0) ?></td>
                        <td>
                            <a href="?remove=<?= (int) $id ?>"
                               class="delete-btn"
                               onclick="return confirm('Remove this item?')">🗑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ORDER SUMMARY -->
    <div class="summary-box">
        <div class="box-header">Order Summary</div>
        <div class="summary-rows">
            <?php foreach ($cart as $item): ?>
                <div class="summary-row">
                    <span><?= htmlspecialchars($item['name']) ?> × <?= (int) $item['qty'] ?></span>
                    <span>Rs <?= number_format($item['price'] * $item['qty'], 0) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($delivery > 0): ?>
                <div class="summary-row">
                    <span>Delivery</span>
                    <span>Rs <?= $delivery ?></span>
                </div>
            <?php endif; ?>

            <div class="summary-row total">
                <span>Total</span>
                <span>Rs <?= number_format($total, 0) ?></span>
            </div>
        </div>

        <?php if (!empty($cart)): ?>
            <a href="/foodbyte/checkout.php" class="checkout-btn">Proceed to Checkout →</a>
        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>