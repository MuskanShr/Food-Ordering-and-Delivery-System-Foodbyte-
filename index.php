<?php
require_once 'includes/db.php'; 
require_once 'includes/auth.php';
$pageTitle = 'Home';

$stmt = $pdo->prepare("
    SELECT i.*, c.name as cat_name 
    FROM items i 
    JOIN categories c ON i.category_id = c.id 
    ORDER BY i.id ASC 
    LIMIT 4
");
$stmt->execute();
$featured = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
.hero {
    background: #F5F0EA;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    overflow: hidden;
    position: relative;
    min-height: 500px;
}
.hero-image {
    flex-shrink: 0;
    width: 100%;
    height: 750px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}
.hero-image img {
    height: 100%;
    width: 100%;
    object-fit: cover;
    object-position: left center;
}
.section { padding: 3.5rem 3rem; }
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.9rem;
    font-weight: 800;
    position: relative;
}
.section-title::after {
    content: '';
    position: absolute;
    left: 0; bottom: -6px;
    width: 50px; height: 3px;
    background: var(--orange);
    border-radius: 2px;
}
.food-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1.5rem;
}
.food-card {
    background: var(--warm-white);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--border);
    transition: all 0.3s;
    box-shadow: var(--shadow);
}
.food-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.food-card-img {
    height: 160px;
    background: linear-gradient(135deg, #FFF0E6, #FFE0CC);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    overflow: hidden;
}
.food-card-img img { width: 100%; height: 100%; object-fit: cover; }
.food-card-body { padding: 1.1rem; }
.food-card-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.35rem; color: var(--charcoal); }
.food-card-desc {
    font-size: 0.82rem; color: var(--gray);
    line-height: 1.5; margin-bottom: 0.9rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.food-card-footer { display: flex; align-items: center; justify-content: space-between; }
.food-price { font-weight: 700; font-size: 1.05rem; color: var(--orange); }
.btn-add-cart {
    background: var(--orange); color: #fff;
    border: none; border-radius: 8px;
    padding: 0.4rem 0.9rem;
    font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    font-family: 'DM Sans', sans-serif;
}
.btn-add-cart:hover { background: var(--orange-dark); transform: scale(1.03); }

/* Cart animation */
@keyframes flyToCart {
    0%   { opacity: 1; transform: scale(1) translate(0, 0); }
    100% { opacity: 0; transform: scale(0.2) translate(var(--tx), var(--ty)); }
}
.fly-dot {
    position: fixed;
    width: 14px; height: 14px;
    background: var(--orange);
    border-radius: 50%;
    pointer-events: none;
    z-index: 9999;
    animation: flyToCart 0.65s cubic-bezier(.4, 0, .2, 1) forwards;
}
@keyframes bump {
    0%   { transform: scale(1); }
    50%  { transform: scale(1.6); }
    100% { transform: scale(1); }
}
.cart-badge-bump { animation: bump 0.3s ease; }
</style>

<!-- HERO -->
<section class="hero">
    <div class="hero-image">
        <img src="uploads/Foodbyte.png" alt="Fresh food delivered fast">
    </div>
</section>

<!-- FEATURED ITEMS -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Customer's Fav</h2>
        <a href="/foodbyte/menu.php" class="btn btn-outline btn-sm">View All</a>
    </div>

    <div class="food-grid">
        <?php foreach ($featured as $item): ?>
        <div class="food-card">
            <div class="food-card-img">
                <?php if ($item['image'] && file_exists('uploads/' . $item['image'])): ?>
                    <img src="/foodbyte/uploads/<?= htmlspecialchars($item['image']) ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    🍽️
                <?php endif; ?>
            </div>
            <div class="food-card-body">
                <div class="food-card-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="food-card-desc"><?= htmlspecialchars($item['description']) ?></div>
                <div class="food-card-footer">
                    <span class="food-price">Rs <?= number_format($item['price'], 0) ?></span>
                    <button class="btn-add-cart" onclick="addToCart(<?= (int)$item['id'] ?>, this)">
                        Add to cart
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
function addToCart(itemId, btn) {
    const btnRect  = btn.getBoundingClientRect();
    const cartLink = document.querySelector('.nav-right a[href*="cart"]');

    const dot = document.createElement('div');
    dot.className  = 'fly-dot';
    dot.style.left = (btnRect.left + btnRect.width  / 2 - 7) + 'px';
    dot.style.top  = (btnRect.top  + btnRect.height / 2 - 7) + 'px';

    if (cartLink) {
        const cartRect = cartLink.getBoundingClientRect();
        dot.style.setProperty('--tx', ((cartRect.left + cartRect.width  / 2) - (btnRect.left + btnRect.width  / 2)) + 'px');
        dot.style.setProperty('--ty', ((cartRect.top  + cartRect.height / 2) - (btnRect.top  + btnRect.height / 2)) + 'px');
    } else {
        dot.style.setProperty('--tx', '400px');
        dot.style.setProperty('--ty', '-300px');
    }

    document.body.appendChild(dot);
    setTimeout(() => dot.remove(), 700);

    fetch('/foodbyte/cart.php?add=' + itemId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;

        let badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = data.cartCount;
            badge.classList.remove('cart-badge-bump');
            void badge.offsetWidth;
            badge.classList.add('cart-badge-bump');
        } else if (cartLink) {
            const b = document.createElement('span');
            b.className   = 'cart-badge';
            b.textContent = data.cartCount;
            cartLink.appendChild(b);
        }

        const orig = btn.textContent;
        btn.textContent      = '✓ Added';
        btn.style.background = '#2E7D32';
        setTimeout(() => {
            btn.textContent      = orig;
            btn.style.background = '';
        }, 1200);
    })
    .catch(() => {
        window.location.href = '/foodbyte/cart.php?add=' + itemId;
    });
}
</script>

<?php include 'includes/footer.php'; ?>