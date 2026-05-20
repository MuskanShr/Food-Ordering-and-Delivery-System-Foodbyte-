<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];

// User details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

// Order stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_orders,
           COALESCE(SUM(total), 0) as total_spent,
           MAX(created_at) as last_order
    FROM orders WHERE user_id = ?
");
$stmt->execute([$uid]);
$stats = $stmt->fetch();

/* Recent orders with item names.
 *
 * We pull each order's items as a comma-separated list using GROUP_CONCAT,
 * plus a count so we can show "+ N more" if the list is long.
 * The internal `id` is kept ONLY for the link target / key — it isn't
 * shown to the user any more.
 */
$stmt = $pdo->prepare("
    SELECT o.id,
           o.total,
           o.status,
           o.created_at,
           COALESCE(
               GROUP_CONCAT(i.name ORDER BY oi.id SEPARATOR '||'),
               ''
           )                       AS item_names,
           COALESCE(SUM(oi.quantity), 0) AS total_qty
    FROM   orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN items       i  ON i.id        = oi.item_id
    WHERE  o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$uid]);
$recentOrders = $stmt->fetchAll();

/**
 * Turn a "Pizza||Burger||Fries" string into a friendly summary like
 * "Pizza, Burger + 1 more" — keeps the row compact and predictable.
 */
function summarizeOrderItems(string $raw, int $maxShown = 2): string {
    if ($raw === '') return 'Order details';
    $items   = array_filter(explode('||', $raw), fn($s) => $s !== '');
    $items   = array_values($items);
    $count   = count($items);
    if ($count === 0) return 'Order details';
    $shown   = array_slice($items, 0, $maxShown);
    $extra   = $count - count($shown);
    $summary = implode(', ', $shown);
    if ($extra > 0) $summary .= ' + ' . $extra . ' more';
    return $summary;
}

// Delivered orders eligible for feedback (not yet reviewed) — also pulls items
$stmt = $pdo->prepare("
    SELECT o.id, o.total, o.created_at,
           COALESCE(GROUP_CONCAT(i.name ORDER BY oi.id SEPARATOR '||'), '') AS item_names
    FROM orders o
    LEFT JOIN feedback   f  ON f.order_id = o.id AND f.user_id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN items       i  ON i.id        = oi.item_id
    WHERE o.user_id = ? AND o.status = 'Delivered' AND f.id IS NULL
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$uid]);
$pendingFeedbackOrders = $stmt->fetchAll();

// Already submitted feedback
$stmt = $pdo->prepare("
    SELECT f.*, o.id as order_num, o.total, o.created_at as order_date,
           COALESCE(GROUP_CONCAT(i.name ORDER BY oi.id SEPARATOR '||'), '') AS item_names
    FROM feedback f
    JOIN orders o ON f.order_id = o.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN items       i  ON i.id        = oi.item_id
    WHERE f.user_id = ?
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
$stmt->execute([$uid]);
$myFeedbacks = $stmt->fetchAll();

// Flash / errors from submit
$feedbackErrors = $_SESSION['feedback_errors'] ?? [];
$feedbackOrder  = $_SESSION['feedback_order']  ?? 0;
unset($_SESSION['feedback_errors'], $_SESSION['feedback_order']);

$pageTitle = 'My Profile';
include 'includes/header.php';
?>
<style>
.profile-wrap {
    max-width: 860px;
    margin: 2.5rem auto;
    padding: 0 2rem 4rem;
}

/* ── Header card ── */
.profile-hero {
    background: linear-gradient(135deg, var(--orange), var(--orange-dark));
    border-radius: 20px;
    padding: 2.5rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 1.8rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(232,82,26,0.28);
}
.profile-hero::before {
    content: '';
    position: absolute;
    right: -60px; top: -60px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.profile-hero::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -80px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
}
.avatar {
    width: 88px; height: 88px;
    border-radius: 50%;
    background: rgba(255,255,255,0.22);
    border: 3px solid rgba(255,255,255,0.5);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.4rem;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
    overflow: hidden;
}
.avatar img {
    width: 100%; height: 100%;
    object-fit: cover;
    border-radius: 50%;
}
.avatar-wrap {
    position: relative;
    flex-shrink: 0;
}
.avatar-edit-btn {
    position: absolute;
    bottom: 0; right: 0;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: white;
    border: 2px solid var(--orange);
    color: var(--orange);
    font-size: 0.75rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: background 0.2s, color 0.2s;
    z-index: 2;
}
.avatar-edit-btn:hover { background: var(--orange); color: white; }
.photo-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.55); z-index: 300;
    align-items: center; justify-content: center;
}
.photo-modal-overlay.show { display: flex; }
.photo-modal {
    background: white; border-radius: 18px;
    padding: 2rem 2rem 1.6rem;
    max-width: 380px; width: 92%;
    box-shadow: 0 16px 56px rgba(0,0,0,0.22);
}
.photo-modal h3 { font-family: 'Playfair Display', serif; font-size: 1.25rem; margin-bottom: 0.4rem; }
.photo-modal p { color: var(--gray); font-size: 0.88rem; margin-bottom: 1.2rem; }
.drop-zone {
    border: 2px dashed var(--border); border-radius: 12px;
    padding: 1.8rem 1rem; text-align: center; cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    margin-bottom: 1rem; position: relative;
}
.drop-zone.drag-over { border-color: var(--orange); background: #fff5f0; }
.drop-zone input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.drop-zone-icon { font-size: 2.2rem; margin-bottom: 0.5rem; }
.drop-zone-text { font-size: 0.88rem; color: var(--gray); }
.drop-zone-text strong { color: var(--orange); }
.drop-zone-hint { font-size: 0.76rem; color: var(--gray); margin-top: 0.3rem; }
.preview-wrap {
    display: none; align-items: center; gap: 0.8rem;
    padding: 0.8rem; border: 1.5px solid var(--border);
    border-radius: 10px; margin-bottom: 1rem;
}
.preview-wrap.show { display: flex; }
.preview-thumb { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--orange); flex-shrink: 0; }
.preview-name { font-size: 0.85rem; font-weight: 600; word-break: break-all; }
.preview-size { font-size: 0.76rem; color: var(--gray); }
.preview-clear { margin-left: auto; background: none; border: none; color: var(--gray); font-size: 1.1rem; cursor: pointer; }
.photo-modal-actions { display: flex; gap: 0.7rem; flex-wrap: wrap; margin-top: 0.5rem; }
.btn-upload { background: var(--orange); color: white; border: none; border-radius: 10px; padding: 0.6rem 1.4rem; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: background 0.2s; flex: 1; }
.btn-upload:hover { background: var(--orange-dark); }
.btn-upload:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-remove { background: #fff0f0; color: #dc2626; border: 1.5px solid #fca5a5; border-radius: 10px; padding: 0.6rem 1.1rem; font-size: 0.88rem; font-weight: 700; cursor: pointer; }
.btn-remove:hover { background: #fee2e2; }
.btn-cancel { background: var(--cream); color: var(--charcoal); border: 1.5px solid var(--border); border-radius: 10px; padding: 0.6rem 1rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; }
.flash-msg { border-radius: 10px; padding: 0.75rem 1.1rem; margin-bottom: 1.2rem; font-weight: 600; font-size: 0.9rem; }
.flash-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
.flash-error   { background: #fff0f0; border: 1px solid #fca5a5; color: #dc2626; }
.profile-meta { color: white; z-index: 1; }
.profile-meta h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem; font-weight: 800;
    margin-bottom: 0.3rem;
}
.profile-meta .email {
    opacity: 0.85; font-size: 0.9rem; margin-bottom: 0.6rem;
}
.role-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.35);
    border-radius: 20px;
    padding: 0.25rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
    color: white;
}

/* ── Stats row ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.8rem;
}
.stat-card {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.4rem 1.2rem;
    text-align: center;
    box-shadow: var(--shadow);
}
.stat-icon { font-size: 1.8rem; margin-bottom: 0.5rem; }
.stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem; font-weight: 800;
    color: var(--orange);
    line-height: 1;
    margin-bottom: 0.3rem;
}
.stat-label { font-size: 0.78rem; color: var(--gray); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Info + Orders grid ── */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 1.4rem;
}

.info-card, .orders-card {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.card-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem; font-weight: 800;
    background: var(--cream);
    display: flex; align-items: center; gap: 0.5rem;
}
.info-list { padding: 0.6rem 0; }
.info-row {
    display: flex;
    align-items: center;
    padding: 0.85rem 1.4rem;
    border-bottom: 1px solid var(--light-gray);
    gap: 0.75rem;
}
.info-row:last-child { border-bottom: none; }
.info-icon { font-size: 1.1rem; width: 24px; flex-shrink: 0; }
.info-content {}
.info-label { font-size: 0.73rem; color: var(--gray); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.info-value { font-size: 0.92rem; font-weight: 600; color: var(--charcoal); margin-top: 1px; }

/* Phone row: editable inline */
.phone-row { align-items: flex-start; }
.phone-edit-trigger {
    background: transparent;
    border: 1.5px solid var(--border);
    color: var(--orange);
    border-radius: 7px;
    padding: 0.3rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    margin-left: auto;
    transition: all 0.2s;
}
.phone-edit-trigger:hover { border-color: var(--orange); background: #FFF6EE; }
.phone-form { margin-top: 0.55rem; display: flex; gap: 0.4rem; flex-wrap: wrap; }
.phone-form input {
    flex: 1; min-width: 160px;
    padding: 0.5rem 0.75rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
    outline: none; transition: border 0.2s;
}
.phone-form input:focus { border-color: var(--orange); }
.phone-form button {
    border: none; border-radius: 8px;
    padding: 0.5rem 0.95rem;
    font-size: 0.82rem; font-weight: 600;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}
.phone-form .save  { background: var(--orange); color: #fff; }
.phone-form .save:hover { background: var(--orange-dark); }
.phone-form .cancel { background: transparent; border: 1.5px solid var(--border); color: var(--gray); }
.phone-form .cancel:hover { border-color: var(--orange); color: var(--orange); }
.phone-empty { color: var(--gray); font-style: italic; font-weight: 500; }
.phone-input-error {
    flex-basis: 100%;
    display: none;
    margin-top: 0.3rem;
    color: #C62828;
    font-size: 0.8rem;
    font-weight: 600;
}
.phone-input-error.show { display: block; }


/* ── Recent orders ── */
.orders-list { padding: 0.6rem 0; }
.order-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.4rem;
    border-bottom: 1px solid var(--light-gray);
    gap: 0.5rem;
}
.order-row:last-child { border-bottom: none; }
.order-summary {
    min-width: 0;          /* lets ellipsis work in a flex child */
    flex: 1;
}
.order-items {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--charcoal);
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.order-meta { font-size: 0.76rem; color: var(--gray); margin-top: 3px; }
.order-meta .qty-pill {
    display: inline-block;
    background: var(--cream);
    color: var(--gray);
    border-radius: 12px;
    padding: 0 0.5rem;
    margin-left: 0.35rem;
    font-weight: 600;
    font-size: 0.72rem;
}
.order-amount { font-weight: 700; color: var(--orange); font-size: 0.92rem; }
.status-pill {
    font-size: 0.7rem; font-weight: 700;
    padding: 0.2rem 0.65rem;
    border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.4px;
    margin-top: 4px;
    display: inline-block;
}
.status-Pending       { background: #FFF3E0; color: #E65100; }
.status-Preparing     { background: #E3F2FD; color: #1565C0; }
.status-Out\ for\ Delivery { background: #F3E5F5; color: #6A1B9A; }
.status-Delivered     { background: #E8F5E9; color: #2E7D32; }

.empty-orders {
    text-align: center; padding: 2.5rem 1rem;
    color: var(--gray); font-size: 0.9rem;
}
.empty-orders .ei { font-size: 2.5rem; margin-bottom: 0.5rem; }

@media (max-width: 680px) {
    .profile-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: repeat(3, 1fr); }
    .profile-hero { flex-direction: column; text-align: center; }
}

/* ── Feedback section ── */
.feedback-section {
    margin-top: 1.8rem;
}
.feedback-section .card-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem; font-weight: 800;
    background: var(--cream);
    display: flex; align-items: center; gap: 0.5rem;
    border-radius: 16px 16px 0 0;
}
.feedback-card {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.feedback-form-wrap {
    padding: 1.4rem;
}
.feedback-order-select {
    margin-bottom: 1.2rem;
}
.feedback-order-select label {
    display: block;
    font-size: 0.8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--gray); margin-bottom: 0.4rem;
}
.feedback-order-select select {
    width: 100%; padding: 0.6rem 0.9rem;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-size: 0.92rem; color: var(--charcoal);
    background: white; outline: none;
}
.feedback-order-select select:focus {
    border-color: var(--orange);
}
.star-row { margin-bottom: 1.2rem; }
.star-row label {
    display: block;
    font-size: 0.8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--gray); margin-bottom: 0.5rem;
}
.stars {
    display: flex; flex-direction: row-reverse;
    gap: 4px; justify-content: flex-end;
}
.stars input { display: none; }
.stars label {
    font-size: 2rem; cursor: pointer;
    color: #ddd; transition: color 0.15s;
    margin: 0; text-transform: none; letter-spacing: 0;
    font-weight: normal;
}
.stars input:checked ~ label,
.stars label:hover,
.stars label:hover ~ label {
    color: #f59e0b;
}
.feedback-comment label {
    display: block;
    font-size: 0.8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--gray); margin-bottom: 0.4rem;
}
.feedback-comment textarea {
    width: 100%; padding: 0.7rem 0.9rem;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-size: 0.92rem; color: var(--charcoal);
    resize: vertical; min-height: 90px;
    font-family: inherit; outline: none;
    box-sizing: border-box;
}
.feedback-comment textarea:focus { border-color: var(--orange); }
.feedback-submit-btn {
    margin-top: 1rem;
    background: var(--orange); color: white;
    border: none; border-radius: 10px;
    padding: 0.65rem 1.6rem;
    font-size: 0.92rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s;
}
.feedback-submit-btn:hover { background: var(--orange-dark); }
.feedback-error {
    background: #fff0f0; border: 1px solid #fca5a5;
    border-radius: 10px; padding: 0.7rem 1rem;
    margin-bottom: 1rem; font-size: 0.88rem; color: #dc2626;
}
.no-pending {
    padding: 1.5rem 1.4rem;
    color: var(--gray); font-size: 0.9rem; text-align: center;
}
.my-feedback-list { padding: 0; }
.my-fb-row {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 0.9rem 1.4rem;
    border-bottom: 1px solid var(--light-gray);
    gap: 1rem;
}
.my-fb-row:last-child { border-bottom: none; }
.fb-stars-display { color: #f59e0b; font-size: 1rem; }
.fb-comment { font-size: 0.88rem; color: var(--charcoal); margin-top: 3px; }
.fb-meta { font-size: 0.76rem; color: var(--gray); margin-top: 4px; }

.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 200;
    align-items: center; justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 400px; width: 90%;
    text-align: center;
    box-shadow: var(--shadow-lg);
}
.modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    margin-bottom: 0.8rem;
}
.modal p { color: var(--gray); margin-bottom: 1.5rem; }
.modal-actions { display: flex; gap: 0.8rem; justify-content: center; }
</style>

<div class="profile-wrap">

    <!-- Flash message (photo upload/remove) -->
    <?php if (!empty($_SESSION['photo_flash'])): ?>
    <div class="flash-msg flash-<?= $_SESSION['photo_flash']['type'] ?>">
        <?= htmlspecialchars($_SESSION['photo_flash']['msg']) ?>
    </div>
    <?php unset($_SESSION['photo_flash']); endif; ?>

    <!-- Hero -->
    <div class="profile-hero">
        <div class="avatar-wrap">
            <div class="avatar">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="/foodbyte/uploads/avatars/<?= htmlspecialchars($user['profile_photo']) ?>"
                         alt="Profile photo of <?= htmlspecialchars($user['username']) ?>">
                <?php else: ?>
                    <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <button class="avatar-edit-btn" onclick="document.getElementById('photoModal').classList.add('show')" title="Change photo">✏️</button>
        </div>
        <div class="profile-meta">
            <h1><?= htmlspecialchars($user['username']) ?></h1>
            <div class="email"> <?= htmlspecialchars($user['email']) ?></div>
            <span class="role-badge"><?= ucfirst($user['role']) ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">🛍️</div>
            <div class="stat-value"><?= $stats['total_orders'] ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">Rs <?= number_format($stats['total_spent'], 0) ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value" style="font-size:1rem; padding-top:4px">
                <?= $stats['last_order'] ? date('M d', strtotime($stats['last_order'])) : '—' ?>
            </div>
            <div class="stat-label">Last Order</div>
        </div>
    </div>

    <!-- Info + Orders -->
    <div class="profile-grid">

        <!-- Account Info -->
        <div class="info-card">
            <div class="card-header">👤 Account Details</div>
            <div class="info-list">
                <div class="info-row">
                    <div class="info-icon">🪪</div>
                    <div class="info-content">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">📧</div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <div class="info-row phone-row">
                    <div class="info-icon">📱</div>
                    <div class="info-content" style="flex:1;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div>
                                <div class="info-label">Phone</div>
                                <div class="info-value" id="phone-display">
                                    <?php if (!empty($user['phone'])): ?>
                                        <?= htmlspecialchars($user['phone']) ?>
                                    <?php else: ?>
                                        <span class="phone-empty">Not set</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="phone-edit-trigger" id="phone-edit-btn">
                                <?= !empty($user['phone']) ? 'Edit' : '➕ Add' ?>
                            </button>
                        </div>
                        <form method="POST" action="update_phone.php" class="phone-form" id="phone-form" style="display:none;">
                            <input type="tel" name="phone" id="phone-input"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="e.g. 9812345678"
                                   maxlength="10"
                                   pattern="^\+?[0-9]{6,9}$|^[0-9]{7,10}$"
                                   title="Up to 10 characters: digits only, optionally starting with +"
                                   required>
                            <button type="submit" class="save">Save</button>
                            <button type="button" class="cancel" id="phone-cancel-btn">Cancel</button>
                            <div class="phone-input-error" id="phone-input-error">Max amount of numbers is 10.</div>
                        </form>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">🎭</div>
                    <div class="info-content">
                        <div class="info-label">Role</div>
                        <div class="info-value"><?= ucfirst($user['role']) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">🗓️</div>
                    <div class="info-content">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                </div>
                <div class="info-row" style="border-bottom:none; padding-top:1rem;">
    <button onclick="document.getElementById('logoutModal').classList.add('show')"
            class="btn btn-danger" style="width:100%; justify-content:center;">
        🚪 Logout
    </button>
</div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="orders-card">
            <div class="card-header">📦 Recent Orders</div>
            <div class="orders-list">
                <?php if (empty($recentOrders)): ?>
                <div class="empty-orders">
                    <div class="ei">🛒</div>
                    <p>No orders yet.<br>
                    <a href="/foodbyte/menu.php" style="color:var(--orange);font-weight:600">Browse the menu →</a></p>
                </div>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order):
                        $summary = summarizeOrderItems($order['item_names']);
                        $qty     = (int)$order['total_qty'];
                    ?>
                    <div class="order-row">
                        <div class="order-summary">
                            <div class="order-items" title="<?= htmlspecialchars(str_replace('||', ', ', $order['item_names'])) ?>">
                                <?= htmlspecialchars($summary) ?>
                            </div>
                            <div class="order-meta">
                                <?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?>
                                <?php if ($qty > 0): ?>
                                <span class="qty-pill"><?= $qty ?> item<?= $qty === 1 ? '' : 's' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div class="order-amount">Rs <?= number_format($order['total'], 0) ?></div>
                            <span class="status-pill status-<?= $order['status'] ?>"><?= $order['status'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ── FEEDBACK SECTION ── -->
<div class="feedback-section" id="feedback">

    <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash-msg flash-<?= $_SESSION['flash']['type'] ?? 'success' ?>">
        <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
    </div>
    <?php unset($_SESSION['flash']); endif; ?>

    <!-- Leave Feedback -->
    <div class="feedback-card" style="margin-bottom:1.4rem;">
        <div class="card-header">⭐ Leave Feedback</div>
        <div class="feedback-form-wrap">
            <?php if (!empty($feedbackErrors)): ?>
            <div class="feedback-error">
                <?php foreach ($feedbackErrors as $e): ?>
                    <div>• <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($pendingFeedbackOrders)): ?>
            <div class="no-pending">
                🎉 No pending orders to review. Once your order is delivered, you can leave feedback here.
            </div>
            <?php else: ?>
            <form method="POST" action="/foodbyte/submit_feedback.php">
                <div class="feedback-order-select">
                    <label>Select Delivered Order</label>
                    <select name="order_id" required>
                        <option value="">— Pick an order —</option>
                        <?php foreach ($pendingFeedbackOrders as $po):
                            $poSummary = summarizeOrderItems($po['item_names']);
                        ?>
                        <option value="<?= $po['id'] ?>"
                            <?= ($feedbackOrder == $po['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($poSummary) ?>
                            — Rs <?= number_format($po['total'], 0) ?>
                            (<?= date('M j, Y', strtotime($po['created_at'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="star-row">
                    <label>Your Rating</label>
                    <div class="stars">
                        <input type="radio" name="rating" id="s5" value="5" required>
                        <label for="s5">★</label>
                        <input type="radio" name="rating" id="s4" value="4">
                        <label for="s4">★</label>
                        <input type="radio" name="rating" id="s3" value="3">
                        <label for="s3">★</label>
                        <input type="radio" name="rating" id="s2" value="2">
                        <label for="s2">★</label>
                        <input type="radio" name="rating" id="s1" value="1">
                        <label for="s1">★</label>
                    </div>
                </div>

                <div class="feedback-comment">
                    <label>Your Comment</label>
                    <textarea name="comment" placeholder="Tell us about your experience with the food and delivery…" required></textarea>
                </div>

                <button type="submit" class="feedback-submit-btn">Submit Feedback</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Past Feedback -->
    <?php if (!empty($myFeedbacks)): ?>
    <div class="feedback-card">
        <div class="card-header">📝 My Past Feedback</div>
        <div class="my-feedback-list">
            <?php foreach ($myFeedbacks as $fb):
                $fbSummary = summarizeOrderItems($fb['item_names']);
            ?>
            <div class="my-fb-row">
                <div>
                    <div class="fb-stars-display">
                        <?= str_repeat('★', $fb['rating']) ?><?= str_repeat('☆', 5 - $fb['rating']) ?>
                    </div>
                    <div class="fb-comment"><?= htmlspecialchars($fb['comment']) ?></div>
                    <div class="fb-meta">
                        <?= htmlspecialchars($fbSummary) ?>
                        · <?= date('M j, Y', strtotime($fb['created_at'])) ?>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <span style="font-size:1.3rem;font-weight:800;color:var(--orange);"><?= $fb['rating'] ?>/5</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="logoutModal">
    <div class="modal">
        <h3>Logging out?</h3>
        <p>Do you want to logout?</p>
        <div class="modal-actions">
            <a href="/foodbyte/logout.php" class="btn btn-danger">Yes, Logout</a>
            <button class="btn btn-outline"
                    onclick="document.getElementById('logoutModal').classList.remove('show')">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Photo Upload Modal (with crop) -->
<style>
/* Crop modal overrides */
.photo-modal { max-width: 460px; }

/* Step indicators */
.crop-steps {
    display: flex; gap: 0; margin-bottom: 1.2rem;
    border-radius: 10px; overflow: hidden;
    border: 1.5px solid var(--border);
}
.crop-step {
    flex: 1; padding: 0.45rem 0.5rem; text-align: center;
    font-size: 0.76rem; font-weight: 700; letter-spacing: 0.3px;
    background: var(--cream); color: var(--gray);
    border-right: 1.5px solid var(--border);
    transition: background 0.2s, color 0.2s;
}
.crop-step:last-child { border-right: none; }
.crop-step.active { background: var(--orange); color: white; }
.crop-step.done   { background: #e8f5e9; color: #2e7d32; }

/* Crop canvas container */
.crop-container {
    display: none;
    position: relative;
    width: 100%;
    border-radius: 12px;
    overflow: hidden;
    background: #111;
    margin-bottom: 0.9rem;
    cursor: crosshair;
    user-select: none;
}
.crop-container.show { display: block; }
#cropCanvas { display: block; width: 100%; touch-action: none; }

/* Selection overlay drawn on top via JS - no extra element needed */

/* Circular preview */
.crop-preview-row {
    display: none; align-items: center; gap: 1rem;
    margin-bottom: 0.9rem;
    padding: 0.8rem; background: var(--cream);
    border-radius: 12px; border: 1.5px solid var(--border);
}
.crop-preview-row.show { display: flex; }
.crop-preview-circle {
    width: 72px; height: 72px; border-radius: 50%;
    overflow: hidden; flex-shrink: 0;
    border: 3px solid var(--orange);
    box-shadow: 0 2px 12px rgba(232,82,26,0.25);
}
#cropPreviewCanvas { width: 72px; height: 72px; }
.crop-preview-label { font-size: 0.82rem; color: var(--gray); }
.crop-preview-label strong { display: block; color: var(--charcoal); font-size: 0.9rem; margin-bottom: 2px; }

/* Zoom slider */
.crop-zoom-row {
    display: none; align-items: center; gap: 0.6rem;
    margin-bottom: 0.9rem; font-size: 0.82rem; color: var(--gray);
}
.crop-zoom-row.show { display: flex; }
.crop-zoom-row input[type=range] { flex: 1; accent-color: var(--orange); }
</style>

<div class="photo-modal-overlay" id="photoModal">
    <div class="photo-modal">
        <h3>📸 Profile Photo</h3>

        <!-- Step indicator -->
        <div class="crop-steps">
            <div class="crop-step active" id="step1Label">1 · Choose</div>
            <div class="crop-step"        id="step2Label">2 · Crop</div>
            <div class="crop-step"        id="step3Label">3 · Done</div>
        </div>

        <!-- STEP 1: Drop zone -->
        <div class="drop-zone" id="dropZone">
            <input type="file" name="profile_photo" id="photoInput" accept="image/jpeg,image/png">
            <div class="drop-zone-icon">🖼️</div>
            <div class="drop-zone-text"><strong>Click to browse</strong> or drag & drop</div>
            <div class="drop-zone-hint">JPG / PNG · Max 2 MB</div>
        </div>

        <!-- STEP 2: Crop canvas -->
        <div class="crop-container" id="cropContainer">
            <canvas id="cropCanvas"></canvas>
        </div>

        <!-- Zoom slider -->
        <div class="crop-zoom-row" id="zoomRow">
            <span>🔍</span>
            <input type="range" id="zoomSlider" min="0.1" max="3" step="0.01" value="1">
            <span>Zoom</span>
        </div>

        <!-- STEP 3: Circular preview -->
        <div class="crop-preview-row" id="previewRow">
            <div class="crop-preview-circle">
                <canvas id="cropPreviewCanvas" width="72" height="72"></canvas>
            </div>
            <div class="crop-preview-label">
                <strong>Preview</strong>
                Looks good? Hit <em>Upload</em>.
            </div>
        </div>

        <!-- Action buttons (change per step) -->
        <div class="photo-modal-actions" id="modalActions">
            <!-- injected by JS -->
        </div>

        <!-- Hidden form posts cropped blob -->
        <form id="photoUploadForm" method="POST" action="/foodbyte/upload_photo.php" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="profile_photo_data" id="croppedDataInput">
        </form>

        <!-- Remove form -->
        <form id="removePhotoForm" method="POST" action="/foodbyte/upload_photo.php" style="display:none;">
            <input type="hidden" name="remove_photo" value="1">
        </form>
    </div>
</div>

<script>
// ─── State ────────────────────────────────────────────────────
let _img        = null;   // original Image object
let _zoom       = 1;
let _panX       = 0;
let _panY       = 0;
let _dragging   = false;
let _lastX      = 0;
let _lastY      = 0;
let _step       = 1;      // 1=choose, 2=crop, 3=preview
const CANVAS_W  = 420;
const CANVAS_H  = 320;
const CIRCLE_R  = 130;    // radius of the crop circle in canvas pixels
const CX        = CANVAS_W / 2;
const CY        = CANVAS_H / 2;
let _hasPhoto   = <?= !empty($user['profile_photo']) ? 'true' : 'false' ?>;

// ─── Elements ─────────────────────────────────────────────────
const photoModal    = document.getElementById('photoModal');
const dropZone      = document.getElementById('dropZone');
const photoInput    = document.getElementById('photoInput');
const cropContainer = document.getElementById('cropContainer');
const cropCanvas    = document.getElementById('cropCanvas');
const zoomRow       = document.getElementById('zoomRow');
const zoomSlider    = document.getElementById('zoomSlider');
const previewRow    = document.getElementById('previewRow');
const previewCanvas = document.getElementById('cropPreviewCanvas');
const modalActions  = document.getElementById('modalActions');
const ctx           = cropCanvas.getContext('2d');
const pCtx          = previewCanvas.getContext('2d');
const s1            = document.getElementById('step1Label');
const s2            = document.getElementById('step2Label');
const s3            = document.getElementById('step3Label');

cropCanvas.width  = CANVAS_W;
cropCanvas.height = CANVAS_H;

// ─── Step rendering ───────────────────────────────────────────
function setStep(n) {
    _step = n;
    [s1,s2,s3].forEach((el,i) => {
        el.classList.toggle('active', i+1 === n);
        el.classList.toggle('done',   i+1 <  n);
    });
    dropZone.style.display      = n === 1 ? '' : 'none';
    cropContainer.classList.toggle('show', n === 2);
    zoomRow.classList.toggle('show',       n === 2);
    previewRow.classList.toggle('show',    n === 3);
    renderActions();
}

function renderActions() {
    let html = '';
    if (_step === 1) {
        if (_hasPhoto) html += '<button type="button" class="btn-remove" id="removePhotoBtn">Remove Photo</button>';
        html += '<button type="button" class="btn-cancel" onclick="closePhotoModal()">Cancel</button>';
    } else if (_step === 2) {
        html += '<button type="button" class="btn-upload" onclick="goPreview()">Next: Preview →</button>';
        html += '<button type="button" class="btn-cancel" onclick="setStep(1)">← Back</button>';
    } else if (_step === 3) {
        html += '<button type="button" class="btn-upload" onclick="doUpload()">✅ Upload Photo</button>';
        html += '<button type="button" class="btn-cancel" onclick="setStep(2)">← Re-crop</button>';
    }
    modalActions.innerHTML = html;
    // Re-bind remove btn
    const rb = document.getElementById('removePhotoBtn');
    if (rb) rb.addEventListener('click', function() {
        if (confirm('Remove your profile photo?')) document.getElementById('removePhotoForm').submit();
    });
}

// ─── Open / close ─────────────────────────────────────────────
document.querySelector('.avatar-edit-btn').addEventListener('click', () => {
    resetCropper();
    photoModal.classList.add('show');
});

function closePhotoModal() {
    photoModal.classList.remove('show');
    resetCropper();
}

function resetCropper() {
    _img = null; _zoom = 1; _panX = 0; _panY = 0;
    photoInput.value = '';
    zoomSlider.value = 1;
    setStep(1);
}

photoModal.addEventListener('click', e => { if (e.target === photoModal) closePhotoModal(); });

// ─── File selection ───────────────────────────────────────────
photoInput.addEventListener('change', () => handleFile(photoInput.files[0]));

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    handleFile(e.dataTransfer.files[0]);
});

function handleFile(file) {
    if (!file) return;
    if (!['image/jpeg','image/png'].includes(file.type)) { alert('Only JPG and PNG images are allowed.'); return; }
    if (file.size > 2 * 1024 * 1024) { alert('File is too large. Maximum size is 2 MB.'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        const img = new Image();
        img.onload = () => {
            _img  = img;
            // Fit image so it fills the circle nicely
            const scale = Math.max((CIRCLE_R * 2) / img.width, (CIRCLE_R * 2) / img.height);
            _zoom = scale;
            zoomSlider.min   = (scale * 0.5).toFixed(2);
            zoomSlider.max   = (scale * 4).toFixed(2);
            zoomSlider.step  = (scale * 0.01).toFixed(4);
            zoomSlider.value = scale;
            _panX = 0; _panY = 0;
            setStep(2);
            drawCrop();
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// ─── Draw crop canvas ─────────────────────────────────────────
function drawCrop() {
    if (!_img) return;
    ctx.clearRect(0, 0, CANVAS_W, CANVAS_H);

    const w = _img.width  * _zoom;
    const h = _img.height * _zoom;
    const x = CX - w/2 + _panX;
    const y = CY - h/2 + _panY;

    // Draw image
    ctx.drawImage(_img, x, y, w, h);

    // Dark overlay outside circle
    ctx.save();
    ctx.fillStyle = 'rgba(0,0,0,0.52)';
    ctx.beginPath();
    ctx.rect(0, 0, CANVAS_W, CANVAS_H);
    ctx.arc(CX, CY, CIRCLE_R, 0, Math.PI*2, true); // cut out circle (counter-clockwise)
    ctx.fill();
    ctx.restore();

    // Circle border
    ctx.save();
    ctx.strokeStyle = '#E8521A';
    ctx.lineWidth   = 2.5;
    ctx.setLineDash([6, 4]);
    ctx.beginPath();
    ctx.arc(CX, CY, CIRCLE_R, 0, Math.PI*2);
    ctx.stroke();
    ctx.restore();

    // Hint text
    ctx.save();
    ctx.fillStyle = 'rgba(255,255,255,0.7)';
    ctx.font = '13px DM Sans, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Drag to reposition · scroll to zoom', CX, CANVAS_H - 10);
    ctx.restore();
}

// ─── Pan (mouse + touch) ──────────────────────────────────────
cropCanvas.addEventListener('mousedown',  e => { _dragging=true; _lastX=e.clientX; _lastY=e.clientY; });
window.addEventListener('mouseup',        () => _dragging = false);
window.addEventListener('mousemove', e => {
    if (!_dragging || !_img) return;
    _panX += e.clientX - _lastX;
    _panY += e.clientY - _lastY;
    _lastX = e.clientX; _lastY = e.clientY;
    clampPan(); drawCrop();
});

cropCanvas.addEventListener('touchstart', e => {
    if (e.touches.length === 1) { _dragging=true; _lastX=e.touches[0].clientX; _lastY=e.touches[0].clientY; }
}, {passive:true});
window.addEventListener('touchend',  () => _dragging = false, {passive:true});
window.addEventListener('touchmove', e => {
    if (!_dragging || !_img || e.touches.length !== 1) return;
    _panX += e.touches[0].clientX - _lastX;
    _panY += e.touches[0].clientY - _lastY;
    _lastX = e.touches[0].clientX; _lastY = e.touches[0].clientY;
    clampPan(); drawCrop();
}, {passive:true});

// ─── Scroll to zoom ───────────────────────────────────────────
cropCanvas.addEventListener('wheel', e => {
    e.preventDefault();
    const minZ = parseFloat(zoomSlider.min);
    const maxZ = parseFloat(zoomSlider.max);
    _zoom = Math.min(maxZ, Math.max(minZ, _zoom - e.deltaY * 0.001 * _zoom));
    zoomSlider.value = _zoom;
    clampPan(); drawCrop();
}, {passive:false});

zoomSlider.addEventListener('input', () => {
    _zoom = parseFloat(zoomSlider.value);
    clampPan(); drawCrop();
});

// ─── Keep image inside the circle (soft clamp) ────────────────
function clampPan() {
    if (!_img) return;
    const hw = _img.width  * _zoom / 2;
    const hh = _img.height * _zoom / 2;
    // Image edge must stay inside circle radius
    const maxX = hw - CIRCLE_R;
    const maxY = hh - CIRCLE_R;
    _panX = Math.max(-maxX, Math.min(maxX, _panX));
    _panY = Math.max(-maxY, Math.min(maxY, _panY));
}

// ─── Step 3: render circular preview ─────────────────────────
function goPreview() {
    renderPreview();
    setStep(3);
}

function renderPreview() {
    const SIZE = 72;
    pCtx.clearRect(0, 0, SIZE, SIZE);
    // Clip to circle
    pCtx.save();
    pCtx.beginPath();
    pCtx.arc(SIZE/2, SIZE/2, SIZE/2, 0, Math.PI*2);
    pCtx.clip();

    const scale = SIZE / (CIRCLE_R * 2);
    const w = _img.width  * _zoom * scale;
    const h = _img.height * _zoom * scale;
    const x = SIZE/2 - w/2 + _panX * scale;
    const y = SIZE/2 - h/2 + _panY * scale;
    pCtx.drawImage(_img, x, y, w, h);
    pCtx.restore();
}

// ─── Upload: export crop → hidden input → submit ──────────────
function doUpload() {
    // Export the circle region to a 400×400 canvas
    const OUT = 400;
    const outCanvas = document.createElement('canvas');
    outCanvas.width = outCanvas.height = OUT;
    const octx = outCanvas.getContext('2d');
    octx.beginPath();
    octx.arc(OUT/2, OUT/2, OUT/2, 0, Math.PI*2);
    octx.clip();

    const scale = OUT / (CIRCLE_R * 2);
    const w = _img.width  * _zoom * scale;
    const h = _img.height * _zoom * scale;
    const x = OUT/2 - w/2 + _panX * scale;
    const y = OUT/2 - h/2 + _panY * scale;
    octx.drawImage(_img, x, y, w, h);

    document.getElementById('croppedDataInput').value = outCanvas.toDataURL('image/jpeg', 0.92);
    document.getElementById('photoUploadForm').submit();
}
</script>
<script>
(function () {
    const editBtn   = document.getElementById('phone-edit-btn');
    const cancelBtn = document.getElementById('phone-cancel-btn');
    const form      = document.getElementById('phone-form');
    const display   = document.getElementById('phone-display');
    const input     = document.getElementById('phone-input');
    const liveErr   = document.getElementById('phone-input-error');
    if (!editBtn || !form) return;

    editBtn.addEventListener('click', function () {
        form.style.display = 'flex';
        display.style.display = 'none';
        editBtn.style.display = 'none';
        input.focus();
        input.select();
    });

    cancelBtn.addEventListener('click', function () {
        form.style.display = 'none';
        display.style.display = '';
        editBtn.style.display = '';
        input.value = input.defaultValue;
        if (liveErr) liveErr.classList.remove('show');
    });

    // Live max-length warning
    if (input && liveErr) {
        input.addEventListener('input', function () {
            if (input.value.length >= 10) liveErr.classList.add('show');
            else                          liveErr.classList.remove('show');
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>