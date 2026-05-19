<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Menu';

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// Active category
$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : ($categories[0]['id'] ?? 0);

// Get items for selected category
$stmt = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY id");
$stmt->execute([$catId]);
$items = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
.menu-layout {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 2rem;
    padding: 2.5rem 3rem;
    min-height: calc(100vh - 200px);
}
.category-sidebar {
    position: sticky;
    top: 84px;
    height: fit-content;
}
.category-sidebar h3 {
    font-family:'Playfair Display',serif;
    font-size:0.95rem; font-weight:800;
    text-transform:uppercase; letter-spacing:1px;
    color:var(--gray);
    margin-bottom:0.8rem;
    padding-bottom:0.6rem;
    border-bottom:1px solid var(--border);
}
.cat-link {
    display:block;
    padding:0.6rem 0.9rem;
    border-radius:10px;
    text-decoration:none;
    font-size:0.92rem; font-weight:500;
    color:var(--charcoal);
    margin-bottom:0.25rem;
    transition:all 0.2s;
    border-left: 3px solid transparent;
}
.cat-link:hover { background:var(--light-gray); color:var(--orange); }
.cat-link.active {
    background: #FFF0E8;
    color:var(--orange);
    font-weight:700;
    border-left-color:var(--orange);
}

.menu-content h2 {
    font-family:'Playfair Display',serif;
    font-size:1.7rem; font-weight:800;
    margin-bottom:1.5rem;
    padding-bottom:0.8rem;
    border-bottom:2px solid var(--border);
    position:relative;
}
.menu-content h2::after {
    content:'';
    position:absolute; left:0; bottom:-2px;
    width:60px; height:2px;
    background:var(--orange);
}
.items-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(220px,1fr));
    gap:1.5rem;
}
.item-card {
    background:var(--warm-white);
    border-radius:14px;
    overflow:hidden;
    border:1px solid var(--border);
    transition:all 0.3s;
    box-shadow:var(--shadow);
}
.item-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); }
.item-card-img {
    height:150px;
    background:linear-gradient(135deg,#FFF0E6,#FFE4CC);
    display:flex; align-items:center; justify-content:center;
    font-size:3.5rem;
    overflow:hidden;
}
.item-card-img img { width:100%; height:100%; object-fit:cover; }
.item-card-body { padding:1rem; }
.item-card-name { font-weight:700; font-size:0.95rem; margin-bottom:0.3rem; }
.item-card-desc { font-size:0.8rem; color:var(--gray); line-height:1.5; margin-bottom:0.8rem;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.item-card-footer { display:flex; align-items:center; justify-content:space-between; }
.item-price { font-weight:700; color:var(--orange); }
.btn-add-cart {
    background:var(--orange); color:#fff;
    border:none; border-radius:7px;
    padding:0.35rem 0.8rem;
    font-size:0.8rem; font-weight:600;
    cursor:pointer; transition:all 0.2s;
    font-family:'DM Sans',sans-serif;
    text-decoration:none;
}
.btn-add-cart:hover { background:var(--orange-dark); }
.empty-state {
    text-align:center; padding:4rem 2rem;
    color:var(--gray);
}
.empty-state .emoji { font-size:3rem; margin-bottom:1rem; }
</style>

<div class="menu-layout">
    <!-- SIDEBAR -->
    <aside class="category-sidebar">
        <h3>Categories</h3>
        <?php foreach($categories as $cat): ?>
        <a href="?cat=<?= $cat['id'] ?>" class="cat-link <?= $cat['id']==$catId?'active':'' ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <!-- ITEMS -->
    <main class="menu-content">
        <?php
        $activeCat = array_filter($categories, fn($c)=>$c['id']==$catId);
        $activeCat = reset($activeCat);
        ?>
        <h2><?= $activeCat ? htmlspecialchars($activeCat['name']) : 'Menu' ?></h2>

        <?php if(empty($items)): ?>
        <div class="empty-state">
            <div class="emoji"></div>
            <p>No items in this category yet.</p>
        </div>
        <?php else: ?>
        <div class="items-grid">
            <?php foreach($items as $item): ?>
            <div class="item-card">
                <div class="item-card-img">
                    <?php if($item['image'] && file_exists('uploads/'.$item['image'])): ?>
                        <img src="/foodbyte/uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
                    <?php else: ?><?php endif; ?>
                </div>
                <div class="item-card-body">
                    <div class="item-card-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="item-card-desc"><?= htmlspecialchars($item['description']) ?></div>
                    <div class="item-card-footer">
                        <span class="item-price">Rs <?= number_format($item['price'],0) ?></span>
                        <button class="btn-add-cart" onclick="addToCart(<?= $item['id'] ?>, this)">Add to cart</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>


<style>
@keyframes flyToCart {
  0%   { opacity: 1; transform: scale(1) translate(0,0); }
  100% { opacity: 0; transform: scale(0.2) translate(var(--tx), var(--ty)); }
}
.fly-dot {
  position: fixed;
  width: 14px; height: 14px;
  background: var(--orange);
  border-radius: 50%;
  pointer-events: none;
  z-index: 9999;
  animation: flyToCart 0.65s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes bump {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.6); }
  100% { transform: scale(1); }
}
.cart-badge-bump { animation: bump 0.3s ease; }
</style>

<script>
function addToCart(itemId, btn) {
  const btnRect = btn.getBoundingClientRect();
  const cartLink = document.querySelector('.nav-right a[href*="cart"]');

  // Flying dot
  const dot = document.createElement('div');
  dot.className = 'fly-dot';
  dot.style.left = (btnRect.left + btnRect.width / 2 - 7) + 'px';
  dot.style.top  = (btnRect.top  + btnRect.height / 2 - 7) + 'px';

  if (cartLink) {
    const cartRect = cartLink.getBoundingClientRect();
    const tx = (cartRect.left + cartRect.width / 2) - (btnRect.left + btnRect.width / 2);
    const ty = (cartRect.top  + cartRect.height / 2) - (btnRect.top  + btnRect.height / 2);
    dot.style.setProperty('--tx', tx + 'px');
    dot.style.setProperty('--ty', ty + 'px');
  } else {
    dot.style.setProperty('--tx', '400px');
    dot.style.setProperty('--ty', '-300px');
  }
  document.body.appendChild(dot);
  setTimeout(() => dot.remove(), 700);

  // AJAX
  fetch('/foodbyte/cart.php?add=' + itemId, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) return;

    // Update or create badge
    let badge = document.querySelector('.cart-badge');
    if (badge) {
      badge.textContent = data.cartCount;
      badge.classList.remove('cart-badge-bump');
      void badge.offsetWidth;
      badge.classList.add('cart-badge-bump');
    } else if (cartLink) {
      const b = document.createElement('span');
      b.className = 'cart-badge';
      b.textContent = data.cartCount;
      cartLink.appendChild(b);
    }

    // Button feedback
    const orig = btn.textContent;
    btn.textContent = '✓ Added';
    btn.style.background = '#2E7D32';
    setTimeout(() => {
      btn.textContent = orig;
      btn.style.background = '';
    }, 1200);
  })
  .catch(() => {
    window.location.href = '/foodbyte/cart.php?add=' + itemId;
  });
}
</script>

<?php include 'includes/footer.php'; ?>