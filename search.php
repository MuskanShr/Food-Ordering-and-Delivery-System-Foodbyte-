<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Search';

/**
 * Build a price-range WHERE clause + parameter list from min/max inputs.
 * Empty / non-numeric / negative values are treated as "no bound" so the
 * default view shows all products when no range is set (Acceptance #4).
 */
function build_price_filter($minRaw, $maxRaw) {
    $sql = '';
    $params = [];

    $min = ($minRaw !== '' && is_numeric($minRaw) && $minRaw >= 0) ? (float)$minRaw : null;
    $max = ($maxRaw !== '' && is_numeric($maxRaw) && $maxRaw >= 0) ? (float)$maxRaw : null;

    // If both are set but reversed, swap them so the user still gets sensible results.
    if ($min !== null && $max !== null && $min > $max) {
        [$min, $max] = [$max, $min];
    }

    if ($min !== null) { $sql .= ' AND i.price >= ?'; $params[] = $min; }
    if ($max !== null) { $sql .= ' AND i.price <= ?'; $params[] = $max; }

    return [$sql, $params];
}

// ---------- AJAX endpoint ----------
if (isset($_GET['ajax'])) {
    $q      = '%' . trim($_GET['q'] ?? '') . '%';
    $minRaw = $_GET['min_price'] ?? '';
    $maxRaw = $_GET['max_price'] ?? '';
    [$priceSql, $priceParams] = build_price_filter($minRaw, $maxRaw);

    $sql  = "SELECT i.*, c.name as cat_name
             FROM items i
             JOIN categories c ON i.category_id = c.id
             WHERE (i.name LIKE ? OR i.description LIKE ?)
             $priceSql
             ORDER BY i.name
             LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$q, $q], $priceParams));

    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ---------- Normal page load ----------
$query  = trim($_GET['q'] ?? '');
$minRaw = $_GET['min_price'] ?? '';
$maxRaw = $_GET['max_price'] ?? '';
[$priceSql, $priceParams] = build_price_filter($minRaw, $maxRaw);

$items = [];
// Run a query if the user typed a search term OR set any price bound.
if ($query !== '' || $priceSql !== '') {
    $q   = '%' . $query . '%';
    $sql = "SELECT i.*, c.name as cat_name
            FROM items i
            JOIN categories c ON i.category_id = c.id
            WHERE (i.name LIKE ? OR i.description LIKE ?)
            $priceSql
            ORDER BY i.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$q, $q], $priceParams));
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
.search-page {
    padding: 3rem;
    max-width: 900px;
    margin: 0 auto;
    min-height: calc(100vh - 64px - 300px);
}

.search-page h1 {
    font-family:'Playfair Display',serif;
    font-size:2rem; font-weight:800; margin-bottom:1.5rem;
}
.search-bar-wrap { display:flex; gap:0.75rem; margin-bottom:1rem; }
.search-input {
    flex:1; padding:0.85rem 1.2rem;
    border:2px solid var(--border); border-radius:12px;
    font-family:'DM Sans',sans-serif; font-size:1rem;
    outline:none; transition:border 0.2s; background:white;
}
.search-input:focus { border-color:var(--orange); }
.search-btn {
    background:var(--orange); color:white;
    border:none; border-radius:12px;
    padding:0 1.5rem; font-size:1rem; font-weight:700;
    cursor:pointer; font-family:'DM Sans',sans-serif;
}
.search-btn:hover { background:var(--orange-dark); }

/* Price range filter */
.price-filter {
    display:flex; align-items:center; gap:0.6rem;
    flex-wrap:wrap;
    background:white; border:1px solid var(--border);
    border-radius:12px; padding:0.7rem 1rem;
    margin-bottom:2rem;
}
.price-filter-label {
    font-weight:600; font-size:0.9rem; color:var(--gray);
    margin-right:0.3rem;
}
.price-input {
    width:110px; padding:0.55rem 0.7rem;
    border:2px solid var(--border); border-radius:8px;
    font-family:'DM Sans',sans-serif; font-size:0.9rem;
    outline:none; transition:border 0.2s;
}
.price-input:focus { border-color:var(--orange); }
.price-sep { color:var(--gray); font-weight:600; }
.price-clear {
    margin-left:auto;
    background:transparent; color:var(--orange);
    border:none; cursor:pointer;
    font-family:'DM Sans',sans-serif; font-size:0.85rem; font-weight:600;
}
.price-clear:hover { text-decoration:underline; }

.results-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(220px,1fr));
    gap:1.5rem;
}
.result-card {
    background:white; border-radius:14px;
    border:1px solid var(--border); overflow:hidden;
    box-shadow:var(--shadow); transition:all 0.3s;
}
.result-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.result-img {
    height:130px;
    background:linear-gradient(135deg,#FFF0E6,#FFE4CC);
    display:flex; align-items:center; justify-content:center;
    font-size:3rem; overflow:hidden;
}
.result-img img { width:100%; height:100%; object-fit:cover; }
.result-body { padding:0.9rem; }
.result-name { font-weight:700; font-size:0.95rem; margin-bottom:0.2rem; }
.result-cat { font-size:0.76rem; color:var(--orange); font-weight:600; margin-bottom:0.4rem; }
.result-desc {
    font-size:0.8rem; color:var(--gray); line-height:1.5; margin-bottom:0.7rem;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.result-footer { display:flex; align-items:center; justify-content:space-between; }
.result-price { font-weight:700; color:var(--orange); }
.btn-add-small {
    background:var(--orange); color:white;
    border:none; border-radius:7px;
    padding:0.32rem 0.75rem; font-size:0.78rem; font-weight:600;
    cursor:pointer; font-family:'DM Sans',sans-serif; text-decoration:none;
}
.btn-add-small:hover { background:var(--orange-dark); }
.no-results { text-align:center; padding:3rem; color:var(--gray); }
</style>

<div class="search-page">
    <h1>Search Menu</h1>
    <form method="GET" id="searchForm">
        <div class="search-bar-wrap">
            <input type="text" name="q" id="searchInput" class="search-input"
                   placeholder="Search for chicken, pizza, drinks…"
                   value="<?= htmlspecialchars($query) ?>" autocomplete="off">
            <button type="submit" class="search-btn">Search</button>
        </div>
        <div class="price-filter">
            <span class="price-filter-label">Price range (Rs):</span>
            <input type="number" name="min_price" id="minPrice"
                   class="price-input" placeholder="Min" min="0" step="1"
                   value="<?= htmlspecialchars($minRaw) ?>">
            <span class="price-sep">to</span>
            <input type="number" name="max_price" id="maxPrice"
                   class="price-input" placeholder="Max" min="0" step="1"
                   value="<?= htmlspecialchars($maxRaw) ?>">
            <button type="button" id="clearPrice" class="price-clear">Clear</button>
        </div>
    </form>

    <div id="resultsContainer">
    <?php if($query !== '' || $priceSql !== ''): ?>
        <?php if(empty($items)): ?>
            <div class="no-results"><p>No results found for your filters.</p></div>
        <?php else: ?>
            <p style="margin-bottom:1rem;color:var(--gray);font-size:0.9rem;">
                <?= count($items) ?> result(s)<?= $query !== '' ? ' for "' . htmlspecialchars($query) . '"' : '' ?>
            </p>
            <div class="results-grid">
                <?php foreach($items as $item): ?>
                <div class="result-card">
                    <div class="result-img">
                        <?php if($item['image'] && file_exists('uploads/'.$item['image'])): ?>
                            <img src="/foodbyte/uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
                        <?php else: ?><?php endif; ?>
                    </div>
                    <div class="result-body">
                        <div class="result-cat"><?= htmlspecialchars($item['cat_name']) ?></div>
                        <div class="result-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="result-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <div class="result-footer">
                            <span class="result-price">Rs <?= number_format($item['price'],0) ?></span>
                            <a href="/foodorder/cart.php?add=<?= $item['id'] ?>" class="btn-add-small">Add to cart</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-results"><p>Start typing or set a price range to search our menu</p></div>
    <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const searchInput = document.getElementById('searchInput');
    const minInput    = document.getElementById('minPrice');
    const maxInput    = document.getElementById('maxPrice');
    const clearBtn    = document.getElementById('clearPrice');
    const container   = document.getElementById('resultsContainer');
    let timer;

    function runSearch() {
        const q   = searchInput.value.trim();
        const min = minInput.value.trim();
        const max = maxInput.value.trim();

        // No criteria at all → restore the default empty state.
        if (q.length < 2 && min === '' && max === '') {
            container.innerHTML =
                '<div class="no-results"><p>Start typing or set a price range to search our menu</p></div>';
            return;
        }

        const params = new URLSearchParams({ ajax: '1', q: q });
        if (min !== '') params.set('min_price', min);
        if (max !== '') params.set('max_price', max);

        fetch('?' + params.toString())
            .then(r => r.json())
            .then(items => {
                if (!items.length) {
                    container.innerHTML =
                        '<div class="no-results"><p>No results found for your filters.</p></div>';
                    return;
                }
                let html = `<p style="margin-bottom:1rem;color:var(--gray);font-size:0.9rem;">${items.length} result(s)</p><div class="results-grid">`;
                items.forEach(item => {
                    const imgHtml = item.image
                        ? `<img src="/foodbyte/uploads/${item.image}" alt="">`
                        : '';
                    html += `
                    <div class="result-card">
                        <div class="result-img">${imgHtml}</div>
                        <div class="result-body">
                            <div class="result-cat">${item.cat_name}</div>
                            <div class="result-name">${item.name}</div>
                            <div class="result-desc">${item.description ?? ''}</div>
                            <div class="result-footer">
                                <span class="result-price">Rs ${parseInt(item.price).toLocaleString()}</span>
                                <a href="/foodorder/cart.php?add=${item.id}" class="btn-add-small">Add to cart</a>
                            </div>
                        </div>
                    </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            })
            .catch(() => {
                container.innerHTML =
                    '<div class="no-results"><p>Something went wrong. Please try again.</p></div>';
            });
    }

    function debouncedSearch() {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 300);
    }

    // Re-run whenever the user types in any field — meets "dynamically adjustable".
    searchInput.addEventListener('input', debouncedSearch);
    minInput.addEventListener('input', debouncedSearch);
    maxInput.addEventListener('input', debouncedSearch);

    clearBtn.addEventListener('click', function () {
        minInput.value = '';
        maxInput.value = '';
        debouncedSearch();
    });
})();
</script>

<?php include 'includes/footer.php'; ?>