<?php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
if ($_SESSION['role'] === 'customer') { header('Location: dashboard.php'); exit; }
$notifCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$userId AND is_read=0")->fetch_assoc()['c'];
$myShops = $conn->query("SELECT * FROM shops WHERE owner_id=$userId ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$pendingOrders = $conn->query("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, s.name AS shop_name
    FROM orders o JOIN shops s ON o.shop_id=s.shop_id JOIN users u ON o.user_id=u.user_id
    WHERE s.owner_id=$userId AND o.status='pending' ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
$totalRevenue = $conn->query("
    SELECT COALESCE(SUM(o.total_amount),0) AS rev FROM orders o JOIN shops s ON o.shop_id=s.shop_id
    WHERE s.owner_id=$userId AND o.status='delivered'
")->fetch_assoc()['rev'];
$totalOrders = $conn->query("
    SELECT COUNT(*) AS cnt FROM orders o JOIN shops s ON o.shop_id=s.shop_id WHERE s.owner_id=$userId
")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LocalLink – Shop Dashboard</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    .content-body { padding: 20px; margin-top: -60px; position: relative; z-index: 5; }
    .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 24px; }
    .orders-list { display: flex; flex-direction: column; gap: 12px; }
    .shops-grid-owner { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 14px; }
    @media(min-width:700px){ .stats-grid{grid-template-columns:repeat(4,1fr);} }
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Local<span>Link</span></div>
    <div><div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div><div class="sidebar-user-info">Shop Owner</div></div>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="shop_dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="add_shop.php"><span class="nav-icon">➕</span> Add New Shop</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php"><span class="nav-icon">🛒</span> All Orders</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications<?php if($notifCount>0):?><span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span><?php endif;?></a>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>

<nav class="navbar-ll">
  <div class="nav-left">
    <button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="nav-brand">Local<span>Link</span></div>
  </div>
  <div class="nav-right">
    <button class="nav-notif" onclick="location.href='notifications.php'">🔔<?php if($notifCount>0):?><span class="notif-badge"><?=$notifCount?></span><?php endif;?></button>
    <div class="nav-avatar" onclick="location.href='profile.php'"><?= strtoupper(substr($userName,0,1)) ?></div>
  </div>
</nav>

<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;">
      <div class="hero-title">Welcome, <span><?= htmlspecialchars(explode(' ',$userName)[0]) ?></span>! 🏪</div>
      <div class="hero-subtitle">Manage your shops, products & orders from here</div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">
    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card stat-card-1"><div class="stat-icon">🏪</div><div><div class="stat-num"><?= count($myShops) ?></div><div class="stat-label">My Shops</div></div></div>
      <div class="stat-card stat-card-2"><div class="stat-icon">📦</div><div><div class="stat-num"><?= $totalOrders ?></div><div class="stat-label">Total Orders</div></div></div>
      <div class="stat-card stat-card-3"><div class="stat-icon">⏳</div><div><div class="stat-num"><?= count($pendingOrders) ?></div><div class="stat-label">Pending</div></div></div>
      <div class="stat-card stat-card-4"><div class="stat-icon">💰</div><div><div class="stat-num">₹<?= number_format($totalRevenue,0) ?></div><div class="stat-label">Revenue</div></div></div>
    </div>

    <!-- PENDING ORDERS -->
    <div class="section-title"><span class="section-title-dot">⚡</span> Pending Orders</div>
    <?php if(empty($pendingOrders)): ?>
      <div class="card-ll" style="padding:24px;text-align:center;margin-bottom:24px;">
        <div style="font-size:2.5rem;margin-bottom:8px;">🎉</div>
        <div style="font-weight:700;color:var(--muted);">No pending orders right now!</div>
      </div>
    <?php else: ?>
    <div class="orders-list" style="margin-bottom:24px;">
      <?php foreach($pendingOrders as $o): ?>
      <div class="card-ll" style="padding:16px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-weight:800;font-size:0.95rem;">Order #<?= $o['order_id'] ?> – <?= htmlspecialchars($o['shop_name']) ?></div>
            <div style="font-size:0.82rem;color:var(--muted);margin-top:2px;">👤 <?= htmlspecialchars($o['customer_name']) ?> &nbsp;|&nbsp; 📞 <?= htmlspecialchars($o['customer_phone']??'N/A') ?></div>
            <div style="font-size:0.82rem;margin-top:2px;">📍 <?= htmlspecialchars($o['delivery_address']??'') ?></div>
            <div style="font-size:0.78rem;color:var(--muted);margin-top:2px;"><?= date('d M Y, h:i A',strtotime($o['created_at'])) ?></div>
          </div>
          <div style="text-align:right;">
            <div style="font-family:var(--font-heading);font-size:1.2rem;font-weight:900;color:var(--primary);">₹<?= number_format($o['total_amount'],2) ?></div>
            <span class="status-badge pending" style="margin-top:6px;display:inline-flex;">⏳ Pending</span>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn-ll btn-primary-ll btn-sm-ll" onclick="updateOrder(<?= $o['order_id'] ?>,'confirmed')">✅ Confirm</button>
          <button class="btn-ll btn-secondary-ll btn-sm-ll" onclick="updateOrder(<?= $o['order_id'] ?>,'preparing')">👨‍🍳 Preparing</button>
          <button class="btn-ll btn-gold-ll btn-sm-ll" onclick="updateOrder(<?= $o['order_id'] ?>,'out_for_delivery')">🚴 Out for Delivery</button>
          <button class="btn-ll btn-sm-ll" style="background:rgba(17,153,142,0.1);color:var(--success);" onclick="updateOrder(<?= $o['order_id'] ?>,'delivered')">🎉 Delivered</button>
          <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="updateOrder(<?= $o['order_id'] ?>,'cancelled')">❌ Cancel</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- MY SHOPS -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div class="section-title" style="margin-bottom:0;"><span class="section-title-dot">🏪</span> My Shops</div>
      <a href="add_shop.php" class="btn-ll btn-primary-ll btn-sm-ll">+ Add Shop</a>
    </div>
    <?php if(empty($myShops)): ?>
      <div class="empty-state">
        <div class="empty-icon">🏗</div>
        <h3>No shops yet</h3>
        <p>Add your first shop to start receiving orders!</p>
        <a href="add_shop.php" class="btn-ll btn-primary-ll" style="margin-top:16px;">➕ Add Shop</a>
      </div>
    <?php else: ?>
    <div class="shops-grid-owner">
      <?php foreach($myShops as $s): ?>
      <div class="card-ll" style="padding:18px;">
        <div style="font-family:var(--font-heading);font-weight:800;font-size:1rem;margin-bottom:4px;"><?= htmlspecialchars($s['name']) ?></div>
        <div style="font-size:0.8rem;color:var(--muted);">📍 <?= htmlspecialchars($s['address']??'') ?></div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;">
          <span class="status-badge <?= $s['is_open']?'delivered':'cancelled' ?>"><?= $s['is_open']?'🟢 Open':'🔴 Closed' ?></span>
          <span style="font-size:0.8rem;color:var(--muted);">⭐ <?= number_format($s['rating'],1) ?></span>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <a href="manage_products.php?shop_id=<?= $s['shop_id'] ?>" class="btn-ll btn-secondary-ll btn-sm-ll" style="flex:1;text-align:center;">📦 Products</a>
          <a href="edit_shop.php?id=<?= $s['shop_id'] ?>" class="btn-ll btn-outline-ll btn-sm-ll">✏️ Edit</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<div id="toast-container"></div>
<script>
async function updateOrder(orderId, status) {
  const res = await fetch('api/update_order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({order_id:orderId, status})
  });
  const data = await res.json();
  if(data.success){ showToast('Order Updated','Status: '+status.replace(/_/g,' '),'success'); setTimeout(()=>location.reload(),1500); }
  else showToast('Error',data.message||'Failed','error');
}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
