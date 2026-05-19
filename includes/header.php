<?php
require_once __DIR__ . '/auth.php';
$cartCount = getCartCount();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodByte <?php echo isset($pageTitle) ? '- '.$pageTitle : ''; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #FAF7F2;
            --warm-white: #FFFEF9;
            --orange: #E8521A;
            --orange-dark: #C94210;
            --orange-light: #FF7A47;
            --charcoal: #1A1A1A;
            --gray: #6B6B6B;
            --light-gray: #E8E4DF;
            --border: #DDD8D0;
            --shadow: 0 4px 24px rgba(26,26,26,0.08);
            --shadow-lg: 0 12px 48px rgba(26,26,26,0.14);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--charcoal); }

        nav {
            background:var(--warm-white);
            border-bottom:1px solid var(--border);
            position:sticky; top:0; z-index:100;
            padding:0 2rem;
            display:flex; align-items:center; justify-content:space-between;
            height:80px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06);
        }
        .nav-brand {
            font-family:'Playfair Display',serif;
            font-size:2rem;
            font-weight:800;
            color:var(--orange);
            text-decoration:none;
            letter-spacing:-0.5px;
        }
        .nav-links {
            display:flex; align-items:center; gap:0.25rem;
            list-style:none;
        }
        .nav-links a {
            text-decoration:none;
            color:var(--charcoal);
            font-size:1rem;
            font-weight:500;
            padding:0.45rem 0.9rem;
            border-radius:8px;
            transition:all 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            background:var(--cream);
            color:var(--orange);
        }
        .nav-right { display:flex; align-items:center; gap:0.5rem; }
        .btn-nav {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.4rem 1rem;
            border-radius:8px;
            font-size:0.88rem; font-weight:600;
            text-decoration:none;
            transition:all 0.2s;
            cursor:pointer; border:none;
        }
        .btn-nav-outline {
            border:1.5px solid var(--border);
            background:transparent;
            color:var(--charcoal);
        }
        .btn-nav-outline:hover { border-color:var(--orange); color:var(--orange); }
        .btn-nav-primary { background:var(--orange); color:#fff; }
        .btn-nav-primary:hover { background:var(--orange-dark); }
        .cart-badge {
            background:var(--orange);
            color:#fff;
            font-size:0.7rem; font-weight:700;
            padding:2px 6px;
            border-radius:20px;
            min-width:20px; text-align:center;
        }
        .user-info {
            font-size:0.85rem;
            color:var(--gray);
            padding:0.3rem 0.8rem;
            background:var(--cream);
            border-radius:8px;
            border:1px solid var(--border);
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap:0.35rem;
            transition:all 0.2s;
        }
        .user-info:hover { border-color:var(--orange); color:var(--orange); }

        /* ---------- Notification bell ---------- */
        .notif-wrap { position:relative; }
        .notif-btn {
            position:relative;
            background:transparent;
            border:1.5px solid var(--border);
            border-radius:8px;
            width:40px; height:38px;
            display:inline-flex; align-items:center; justify-content:center;
            cursor:pointer;
            font-size:1.1rem;
            transition:all 0.2s;
            color:var(--charcoal);
        }
        .notif-btn:hover { border-color:var(--orange); color:var(--orange); }
        .notif-dot {
            position:absolute;
            top:-4px; right:-4px;
            min-width:16px; height:16px;
            padding:0 4px;
            background:#E53935;
            border:2px solid var(--warm-white);
            border-radius:50%;
            color:#fff;
            font-size:0.65rem; font-weight:700;
            display:none;
            align-items:center; justify-content:center;
            line-height:1;
        }
        .notif-dot.show { display:inline-flex; }

        .notif-panel {
            position:absolute;
            top:calc(100% + 10px);
            right:0;
            width:360px;
            max-height:480px;
            background:var(--warm-white);
            border:1px solid var(--border);
            border-radius:14px;
            box-shadow:var(--shadow-lg);
            overflow:hidden;
            display:none;
            flex-direction:column;
            z-index:200;
        }
        .notif-panel.open { display:flex; }
        .notif-header {
            padding:0.9rem 1.1rem;
            border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
            background:var(--cream);
        }
        .notif-header h3 {
            font-family:'Playfair Display',serif;
            font-size:1.1rem; font-weight:700;
        }
        .notif-tabs { display:flex; gap:0.3rem; padding:0.5rem 0.8rem 0; }
        .notif-tab {
            flex:1; text-align:center;
            padding:0.45rem 0.6rem;
            font-size:0.82rem; font-weight:600;
            border:none; background:transparent;
            color:var(--gray); cursor:pointer;
            border-radius:8px;
            font-family:'DM Sans',sans-serif;
        }
        .notif-tab.active {
            background:var(--cream); color:var(--orange);
        }
        .notif-body {
            flex:1;
            overflow-y:auto;
            padding:0.4rem 0.5rem 0.6rem;
        }
        .notif-item {
            padding:0.75rem 0.8rem;
            border-radius:10px;
            margin-bottom:0.3rem;
            border:1px solid transparent;
            transition:background 0.15s;
        }
        .notif-item:hover { background:var(--cream); }
        .notif-item.unread { background:#FFF6EE; border-color:#FFE0CC; }
        .notif-item-title {
            font-weight:700; font-size:0.88rem;
            margin-bottom:0.15rem;
            display:flex; align-items:center; gap:0.4rem;
        }
        .notif-item-msg { font-size:0.82rem; color:var(--gray); line-height:1.4; }
        .notif-item-time { font-size:0.7rem; color:var(--gray); margin-top:0.3rem; }
        .notif-status-pill {
            display:inline-block;
            padding:1px 7px; border-radius:10px;
            font-size:0.65rem; font-weight:700;
            text-transform:uppercase; letter-spacing:0.3px;
        }
        .pill-Pending          { background:#FFF3CD; color:#8A6D00; }
        .pill-Preparing        { background:#FFE0CC; color:var(--orange-dark); }
        .pill-Out              { background:#D6EAFD; color:#0E5BA8; }
        .pill-Delivered        { background:#D7F3DD; color:#1F7A37; }

        .voucher-card {
            border:1.5px dashed var(--orange);
            background:#FFF8F2;
            border-radius:10px;
            padding:0.7rem 0.85rem;
            margin-bottom:0.5rem;
        }
        .voucher-code {
            font-family:'DM Sans',monospace;
            font-weight:700; font-size:0.95rem;
            color:var(--orange);
            letter-spacing:0.5px;
        }
        .voucher-desc { font-size:0.78rem; color:var(--charcoal); margin-top:0.15rem; }
        .voucher-meta { font-size:0.7rem; color:var(--gray); margin-top:0.3rem; }

        .notif-empty {
            text-align:center; padding:2rem 1rem;
            color:var(--gray); font-size:0.85rem;
        }

        .flash {
            padding:0.9rem 1.5rem;
            border-radius:10px;
            margin:1rem 2rem;
            font-size:0.9rem; font-weight:500;
            display:flex; align-items:center; gap:0.5rem;
        }
        .flash-success { background:#E8F5E9; color:#2E7D32; border:1px solid #A5D6A7; }
        .flash-error   { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; }

        .btn {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.6rem 1.4rem;
            border-radius:10px;
            font-family:'DM Sans',sans-serif;
            font-size:0.9rem; font-weight:600;
            cursor:pointer; border:none;
            transition:all 0.2s;
            text-decoration:none;
        }
        .btn-primary { background:var(--orange); color:#fff; }
        .btn-primary:hover { background:var(--orange-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(232,82,26,0.3); }
        .btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--charcoal); }
        .btn-outline:hover { border-color:var(--orange); color:var(--orange); }
        .btn-danger { background:#E53935; color:#fff; }
        .btn-danger:hover { background:#C62828; }
        .btn-sm { padding:0.35rem 0.8rem; font-size:0.8rem; border-radius:7px; }

        footer {
            background:var(--charcoal);
            color:rgba(255,255,255,0.5);
            text-align:center;
            padding:1.2rem;
            font-size:0.82rem;
            margin-top:4rem;
        }
    </style>
</head>
<body>
<nav>
   <a href="/foodbyte/index.php" class="nav-brand">FoodByte</a>
    <ul class="nav-links">
        <li><a href="/foodbyte/index.php" class="<?= $currentPage==='index'?'active':'' ?>">Home</a></li>
        <li><a href="/foodbyte/menu.php" class="<?= $currentPage==='menu'?'active':'' ?>">Menu</a></li>
        <li><a href="/foodbyte/aboutus.php" class="<?= $currentPage==='about'?'active':'' ?>">About Us</a></li>
        <li><a href="/foodbyte/search.php" class="<?= $currentPage==='search'?'active':'' ?>">Search</a></li>
    </ul>
    <div class="nav-right">
        <?php if(isLoggedIn()): ?>
            <a href="/foodbyte/profile.php" class="user-info">
                👤 <?= htmlspecialchars($_SESSION['username']) ?>
            </a>

            <!-- Notification bell (between profile and cart) -->
            <div class="notif-wrap" id="notifWrap">
                <button type="button" class="notif-btn" id="notifBtn" aria-label="Notifications">
                    🔔
                    <span class="notif-dot" id="notifDot"></span>
                </button>
                <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications">
                    <div class="notif-header">
                        <h3>Notifications</h3>
                    </div>
                    <div class="notif-tabs">
                        <button type="button" class="notif-tab active" data-tab="orders">Orders</button>
                        <button type="button" class="notif-tab" data-tab="vouchers">Vouchers</button>
                    </div>
                    <div class="notif-body" id="notifBody">
                        <div class="notif-empty">Loading…</div>
                    </div>
                </div>
            </div>

            <a href="/foodbyte/cart.php" class="btn-nav btn-nav-outline">
                🛒 Cart <?php if($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
        <?php else: ?>
            <a href="/foodbyte/cart.php" class="btn-nav btn-nav-outline">
                🛒 Cart <?php if($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
            <a href="/foodbyte/login.php" class="btn-nav btn-nav-primary">Log In / Register</a>
        <?php endif; ?>
    </div>
</nav>

<?php if(isLoggedIn()): ?>
<script>
(function () {
    const btn    = document.getElementById('notifBtn');
    const panel  = document.getElementById('notifPanel');
    const dot    = document.getElementById('notifDot');
    const body   = document.getElementById('notifBody');
    const wrap   = document.getElementById('notifWrap');
    const tabs   = panel.querySelectorAll('.notif-tab');

    let cache = { notifications: [], vouchers: [] };
    let activeTab = 'orders';

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    function timeAgo(ts) {
        const d = new Date(ts.replace(' ', 'T'));
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60)    return 'just now';
        if (diff < 3600)  return Math.floor(diff/60)   + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    function statusFromTitle(title) {
        // Title format: "Order #N - Status"
        const m = String(title).match(/-\s*(.+)$/);
        if (!m) return null;
        const s = m[1].trim();
        if (s === 'Out for Delivery') return 'Out';
        return s;
    }

    function renderOrders() {
        if (!cache.notifications.length) {
            body.innerHTML = '<div class="notif-empty">No order updates yet.<br>Place an order to see status updates here.</div>';
            return;
        }
        body.innerHTML = cache.notifications.map(n => {
            const pillClass = statusFromTitle(n.title);
            const pill = pillClass
                ? `<span class="notif-status-pill pill-${pillClass}">${escapeHtml(pillClass === 'Out' ? 'Out for Delivery' : pillClass)}</span>`
                : '';
            return `
                <div class="notif-item ${n.read_at ? '' : 'unread'}">
                    <div class="notif-item-title">${escapeHtml(n.title)} ${pill}</div>
                    <div class="notif-item-msg">${escapeHtml(n.message)}</div>
                    <div class="notif-item-time">${timeAgo(n.created_at)}</div>
                </div>`;
        }).join('');
    }

    function renderVouchers() {
        if (!cache.vouchers.length) {
            body.innerHTML = '<div class="notif-empty">No active vouchers right now.<br>Check back soon!</div>';
            return;
        }
        body.innerHTML = cache.vouchers.map(v => {
            const value = v.discount_type === 'percent'
                ? `${parseFloat(v.discount_value)}% off`
                : `Rs ${parseInt(v.discount_value)} off`;
            const minOrder = parseFloat(v.min_order_amount) > 0
                ? `Min order Rs ${parseInt(v.min_order_amount)}`
                : null;
            const expires = v.valid_until
                ? `Expires ${new Date(v.valid_until.replace(' ','T')).toLocaleDateString()}`
                : null;
            const meta = [minOrder, expires].filter(Boolean).join(' • ');
            return `
                <div class="voucher-card">
                    <div class="voucher-code">${escapeHtml(v.code)}</div>
                    <div class="voucher-desc">${escapeHtml(v.description || value)}</div>
                    ${meta ? `<div class="voucher-meta">${escapeHtml(meta)}</div>` : ''}
                </div>`;
        }).join('');
    }

    function render() {
        if (activeTab === 'orders') renderOrders();
        else                        renderVouchers();
    }

    function updateBadge(count) {
        if (count > 0) {
            dot.textContent = count > 9 ? '9+' : count;
            dot.classList.add('show');
        } else {
            dot.classList.remove('show');
        }
    }

    // Lightweight poll — only the unread count, every 20s
    function pollCount() {
        fetch('/foodbyte/notifications.php?action=count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => updateBadge(d.unread || 0))
            .catch(() => { /* silent */ });
    }

    // Full fetch when the panel is opened
    function loadFull() {
        body.innerHTML = '<div class="notif-empty">Loading…</div>';
        fetch('/foodbyte/notifications.php?action=list', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                cache.notifications = d.notifications || [];
                cache.vouchers      = d.vouchers      || [];
                render();
            })
            .catch(() => {
                body.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
            });
    }

    function openPanel() {
        panel.classList.add('open');
        loadFull();
        // Mark everything as read; clears the red dot.
        fetch('/foodbyte/mark_notifications_read.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).then(() => updateBadge(0)).catch(() => {});
    }

    function closePanel() {
        panel.classList.remove('open');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.classList.contains('open')) closePanel();
        else                                   openPanel();
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closePanel();
    });

    // Tab switching
    tabs.forEach(t => {
        t.addEventListener('click', function () {
            tabs.forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            activeTab = this.dataset.tab;
            render();
        });
    });

    // Initial badge + 20s poll
    pollCount();
    setInterval(pollCount, 20000);
})();
</script>
<?php endif; ?>

<?php if(isset($_SESSION['flash'])): ?>
    <div class="flash flash-<?= $_SESSION['flash']['type'] ?>">
        <?= $_SESSION['flash']['msg'] ?>
    </div>
<?php unset($_SESSION['flash']); endif; ?>