<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$adminPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodByte Admin <?php echo isset($pageTitle) ? '- '.$pageTitle : ''; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #FAF7F2;
            --warm-white: #FFFEF9;
            --orange: #E8521A;
            --orange-dark: #C94210;
            --charcoal: #1A1A1A;
            --gray: #6B6B6B;
            --light-gray: #E8E4DF;
            --border: #DDD8D0;
            --sidebar-bg: #1E1E1E;
            --sidebar-text: rgba(255,255,255,0.75);
            --shadow: 0 4px 24px rgba(26,26,26,0.08);
            --shadow-lg: 0 12px 48px rgba(26,26,26,0.14);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--charcoal); display:flex; min-height:100vh; }

        /* SIDEBAR */
        .admin-sidebar {
            width:220px; flex-shrink:0;
            background:var(--sidebar-bg);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; bottom:0;
            z-index:50;
        }
        .sidebar-brand {
            padding:1.5rem 1.5rem 1rem;
            font-family:'Playfair Display',serif;
            font-size:1.4rem; font-weight:800;
            color:var(--orange);
            border-bottom:1px solid rgba(255,255,255,0.1);
            text-decoration:none;
        }
        .sidebar-label {
            font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px;
            color:rgba(255,255,255,0.3);
            padding:1rem 1.5rem 0.4rem;
        }
        .sidebar-link {
            display:flex; align-items:center; gap:0.75rem;
            padding:0.65rem 1.5rem;
            text-decoration:none;
            color:var(--sidebar-text);
            font-size:0.9rem; font-weight:500;
            transition:all 0.2s;
            border-left:3px solid transparent;
        }
        .sidebar-link:hover { background:rgba(255,255,255,0.07); color:white; }
        .sidebar-link.active {
            background:rgba(232,82,26,0.15);
            color:var(--orange);
            border-left-color:var(--orange);
            font-weight:700;
        }
        .sidebar-link .icon { font-size:1.1rem; width:22px; text-align:center; }
        .sidebar-footer {
            margin-top:auto;
            border-top:1px solid rgba(255,255,255,0.1);
            padding:1rem;
        }
        .logout-btn {
            display:flex; align-items:center; gap:0.6rem;
            padding:0.6rem 1rem;
            background:rgba(232,82,26,0.15);
            border:1px solid rgba(232,82,26,0.3);
            border-radius:9px;
            color:var(--orange); font-size:0.88rem; font-weight:600;
            text-decoration:none; cursor:pointer;
            transition:all 0.2s; width:100%; font-family:'DM Sans',sans-serif;
        }
        .logout-btn:hover { background:rgba(232,82,26,0.25); }

        /* MAIN CONTENT */
        .admin-main {
            margin-left:220px;
            flex:1;
            display:flex; flex-direction:column;
            min-height:100vh;
        }
        .admin-topbar {
            background:var(--warm-white);
            border-bottom:1px solid var(--border);
            padding:0.9rem 2rem;
            display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:40;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .admin-topbar h1 {
            font-family:'Playfair Display',serif;
            font-size:1.3rem; font-weight:800;
        }
        .admin-user {
            font-size:0.85rem; color:var(--gray);
            background:var(--cream); border:1px solid var(--border);
            border-radius:8px; padding:0.3rem 0.8rem;
        }
        .admin-content { padding:2rem; flex:1; }

        /* CARDS & COMPONENTS */
        .card {
            background:white; border-radius:14px;
            border:1px solid var(--border);
            box-shadow:var(--shadow);
            overflow:hidden;
        }
        .card-header {
            padding:1.1rem 1.5rem;
            border-bottom:1px solid var(--border);
            font-weight:700; font-size:1rem;
            display:flex; align-items:center; justify-content:space-between;
            background:var(--cream);
        }
        .card-body { padding:1.5rem; }

        /* STATS */
        .stats-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:1.2rem; margin-bottom:2rem;
        }
        .stat-card {
            background:white; border-radius:14px;
            padding:1.4rem; border:1px solid var(--border);
            box-shadow:var(--shadow);
        }
        .stat-label { font-size:0.8rem; text-transform:uppercase; letter-spacing:0.8px; color:var(--gray); margin-bottom:0.5rem; }
        .stat-value { font-family:'Playfair Display',serif; font-size:2rem; font-weight:800; color:var(--charcoal); }
        .stat-card.accent .stat-value { color:var(--orange); }

        /* TABLE */
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th {
            text-align:left; padding:0.75rem 1rem;
            font-size:0.78rem; text-transform:uppercase; letter-spacing:0.8px;
            color:var(--gray); background:var(--cream);
            border-bottom:1px solid var(--border);
        }
        .data-table td { padding:0.85rem 1rem; border-bottom:1px solid var(--light-gray); font-size:0.9rem; vertical-align:middle; }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:#FFFAF6; }

        /* FORM */
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .form-group { margin-bottom:1rem; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { display:block; font-size:0.83rem; font-weight:600; margin-bottom:0.4rem; color:var(--gray); }
        .form-control {
            width:100%; padding:0.65rem 0.9rem;
            border:1.5px solid var(--border); border-radius:9px;
            font-family:'DM Sans',sans-serif; font-size:0.9rem;
            outline:none; transition:border 0.2s; background:white;
        }
        .form-control:focus { border-color:var(--orange); }
        textarea.form-control { resize:vertical; min-height:80px; }

        /* BUTTONS */
        .btn { display:inline-flex; align-items:center; gap:0.4rem; padding:0.5rem 1.1rem; border-radius:9px; font-family:'DM Sans',sans-serif; font-size:0.87rem; font-weight:600; cursor:pointer; border:none; transition:all 0.2s; text-decoration:none; }
        .btn-primary { background:var(--orange); color:#fff; }
        .btn-primary:hover { background:var(--orange-dark); }
        .btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--charcoal); }
        .btn-outline:hover { border-color:var(--orange); color:var(--orange); }
        .btn-danger { background:#E53935; color:#fff; }
        .btn-danger:hover { background:#C62828; }
        .btn-success { background:#388E3C; color:#fff; }
        .btn-success:hover { background:#2E7D32; }
        .btn-sm { padding:0.3rem 0.7rem; font-size:0.78rem; border-radius:7px; }

        /* BADGE */
        .badge { display:inline-block; padding:0.25rem 0.6rem; border-radius:20px; font-size:0.74rem; font-weight:700; }
        .badge-pending   { background:#FFF3E0; color:#E65100; }
        .badge-preparing { background:#E3F2FD; color:#1565C0; }
        .badge-delivery  { background:#E8F5E9; color:#2E7D32; }
        .badge-delivered { background:#F3E5F5; color:#6A1B9A; }

        /* FLASH */
        .flash { padding:0.9rem 1.2rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem; font-weight:500; }
        .flash-success { background:#E8F5E9; color:#2E7D32; border:1px solid #A5D6A7; }
        .flash-error   { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; }

        /* FOOTER */
        .admin-footer { padding:1rem 2rem; text-align:center; font-size:0.8rem; color:var(--gray); border-top:1px solid var(--border); background:white; }

        /* MODAL */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal { background:white; border-radius:16px; padding:2rem; max-width:400px; width:90%; text-align:center; box-shadow:var(--shadow-lg); }
        .modal h3 { font-family:'Playfair Display',serif; font-size:1.3rem; margin-bottom:0.8rem; }
        .modal p { color:var(--gray); margin-bottom:1.5rem; }
        .modal-actions { display:flex; gap:0.8rem; justify-content:center; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="admin-sidebar">
    <a href="/foodbyte/admin/dashboard.php" class="sidebar-brand">FoodByte</a>
    <span class="sidebar-label">Main</span>
    <a href="/foodbyte/admin/dashboard.php" class="sidebar-link <?= $adminPage==='dashboard'?'active':'' ?>">
         Dashboard
    </a>
    <span class="sidebar-label">Manage</span>
    <a href="/foodbyte/admin/categories.php" class="sidebar-link <?= $adminPage==='categories'?'active':'' ?>">
         Categories
    </a>
    <a href="/foodbyte/admin/items.php" class="sidebar-link <?= $adminPage==='items'?'active':'' ?>">
        Items
    </a>
    <a href="/foodbyte/admin/orders.php" class="sidebar-link <?= $adminPage==='orders'?'active':'' ?>">
         Orders
    </a>
    <a href="/foodbyte/admin/vouchers.php" class="sidebar-link <?= $adminPage==='vouchers'?'active':'' ?>">
         Vouchers
    </a>
    <div class="sidebar-footer">
        <button class="logout-btn" onclick="document.getElementById('logoutModal').classList.add('show')">
            Logout
        </button>
    </div>
</aside>

<!-- LOGOUT MODAL -->
<div class="modal-overlay" id="logoutModal">
    <div class="modal">
        <h3>Logging out?</h3>
        <p>Do you want to logout?</p>
        <div class="modal-actions">
            <a href="/foodbyte/logout.php" class="btn btn-danger">Yes, Logout</a>
            <button class="btn btn-outline" onclick="document.getElementById('logoutModal').classList.remove('show')">Cancel</button>
        </div>
    </div>
</div>

<main class="admin-main">
    <div class="admin-topbar">
        <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
        <span class="admin-user">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>
    <div class="admin-content">
<?php if(isset($_SESSION['flash'])): ?>
<div class="flash flash-<?= $_SESSION['flash']['type'] ?>"><?= $_SESSION['flash']['msg'] ?></div>
<?php unset($_SESSION['flash']); endif; ?>
