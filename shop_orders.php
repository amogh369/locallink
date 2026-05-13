<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['role'] !== 'shop_owner') { header('Location: dashboard.php'); exit; }
$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['name'];
$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount = (int)$nc->fetchColumn();

$statusFilter = $_GET['status'] ?? 'all';
$catClause    = ($statusFilter !== 'all') ? "AND o.status = :sf" : '';

$sql = "
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
           s.name AS shop_name, COUNT(oi.item_id) AS item_count
    FROM orders o
    JOIN shops s  ON o.shop_id = s.shop_id
    JOIN users u  ON o.user_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE s.owner_id = :uid $catClause
    GROUP BY o.order_id, o.user_id, o.shop_id, o.total_amount, o.status,
             o.delivery_address, o.delivery_lat, o.delivery_lng, o.notes,
             o.payment_method, o.payment_status, o.created_at, o.updated_at,
             u.name, u.phone, s.name
    ORDER BY o.created_at DESC LIMIT 100
";
$params = [':uid' => $userId];
if ($statusFilter !== 'all') $params[':sf'] = $statusFilter;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – All Orders</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body { padding:20px; margin-top:-60px; position:relative; z-index:5; }
    .items-panel  { display:none; margin-top:14px; border-top:2px dashed var(--border); padding-top:14px; }
    .items-panel.open { display:block; }
    .item-row { display:flex; align-items:center; gap:10px; padding:10px 12px;
                border-radius:var(--radius-md); background:var(--light);
                margin-bottom:8px; }
    .item-row:last-child { margin-bottom:0; }
    .item-icon  { width:40px; height:40px; border-radius:var(--radius-sm);
                  background:white; display:flex; align-items:center;
                  justify-content:center; font-size:1.3rem;
                  box-shadow:var(--shadow-sm); flex-shrink:0; }
    .item-name  { flex:1; font-weight:700; font-size:0.9rem; }
    .item-unit  { font-size:0.78rem; color:var(--muted); margin-top:2px; }
    .item-qty   { background:var(--gradient-main); color:white;
                  border-radius:20px; padding:3px 12px;
                  font-size:0.82rem; font-weight:800; flex-shrink:0; }
    .item-price { font-weight:900; color:var(--primary);
                  font-size:1rem; flex-shrink:0; min-width:70px; text-align:right; }
    .items-total { display:flex; justify-content:space-between; align-items:center;
                   padding:10px 12px; border-radius:var(--radius-md);
                   background:var(--dark); margin-top:10px; }
    .view-items-btn { cursor:pointer; background:white; border:2px solid var(--primary);
                      color:var(--primary); border-radius:var(--radius-lg);
                      padding:7px 16px; font-size:0.82rem; font-weight:700;
                      font-family:var(--font-body); transition:var(--transition); }
    .view-items-btn:hover { background:var(--primary); color:white; }
    .view-items-btn.open  { background:var(--primary); color:white; }
    .spinner-sm { width:22px; height:22px; border-radius:50%;
                  border:3px solid rgba(255,65,108,0.2);
                  border-top-color:var(--primary);
                  animation:spin 0.7s linear infinite; margin:10px auto; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .delivery-info { font-size:0.82rem; color:var(--muted); margin-top:4px; }
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Local<span>Link</span></div>
    <div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div>
    <div class="sidebar-user-info">Shop Owner</div></div>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="shop_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="add_shop.php"><span class="nav-icon">➕</span> Add New Shop</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php" class="active"><span class="nav-icon">🛒</span> All Orders</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications
      <?php if($notifCount>0):?>
        <span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span>
      <?php endif;?>
    </a>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>

<nav class="navbar-ll">
  <div class="nav-left">
    <button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="nav-brand">Local<span>Link</span></div>
  </div>
  <div class="nav-right">
    <button class="nav-notif" onclick="location.href='notifications.php'">🔔
      <?php if($notifCount>0):?><span class="notif-badge"><?=$notifCount?></span><?php endif;?>
    </button>
    <div class="nav-avatar" onclick="location.href='profile.php'"><?=strtoupper(substr($userName,0,1))?></div>
  </div>
</nav>

<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;">
      <button class="back-btn" onclick="history.back()">← Back</button>
      <div class="hero-title">All <span>Orders</span> 🛒</div>
      <div class="hero-subtitle"><?=count($orders)?> order<?=count($orders)!=1?'s':''?></div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">

    <!-- STATUS FILTER PILLS -->
    <div class="filter-pills" style="margin-bottom:20px;">
      <?php
      $statuses = [
        'all'              => '🌟 All',
        'pending'          => '⏳ Pending',
        'confirmed'        => '✅ Confirmed',
        'preparing'        => '👨‍🍳 Preparing',
        'out_for_delivery' => '🚴 On Way',
        'delivered'        => '🎉 Delivered',
        'cancelled'        => '❌ Cancelled'
      ];
      foreach($statuses as $val => $label):
      ?>
      <a href="shop_orders.php?status=<?=$val?>" class="pill <?=$statusFilter===$val?'active':''?>"><?=$label?></a>
      <?php endforeach; ?>
    </div>

    <?php if(empty($orders)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>No orders found</h3>
        <p>No orders match this filter yet.</p>
      </div>
    <?php else: foreach($orders as $o): ?>

    <div class="card-ll" style="margin-bottom:14px;padding:18px;">

      <!-- ORDER HEADER -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
          <div style="font-weight:800;font-size:1rem;">
            Order #<?=$o['order_id']?> &nbsp;—&nbsp; <?=htmlspecialchars($o['shop_name'])?>
          </div>
          <div style="font-size:0.84rem;color:var(--muted);margin-top:4px;">
            👤 <strong><?=htmlspecialchars($o['customer_name'])?></strong>
            &nbsp;|&nbsp; 📞 <?=htmlspecialchars($o['customer_phone'] ?? 'N/A')?>
          </div>
          <?php if($o['delivery_address']): ?>
          <div class="delivery-info">
            📍 <?=htmlspecialchars($o['delivery_address'])?>
          </div>
          <?php endif; ?>
          <div style="font-size:0.78rem;color:var(--muted);margin-top:4px;">
            🕐 <?=date('d M Y, h:i A', strtotime($o['created_at']))?>
            &nbsp;|&nbsp; 💳 <?=ucfirst($o['payment_method'] ?? 'cash')?>
          </div>
        </div>

        <div style="text-align:right;flex-shrink:0;">
          <div style="font-family:var(--font-heading);font-size:1.4rem;font-weight:900;color:var(--primary);">
            ₹<?=number_format($o['total_amount'],2)?>
          </div>
          <span class="status-badge <?=$o['status']?>" style="margin-top:6px;display:inline-flex;">
            <?=ucwords(str_replace('_',' ',$o['status']))?>
          </span>
          <div style="font-size:0.78rem;color:var(--muted);margin-top:6px;">
            <?=$o['item_count']?> item<?=$o['item_count']!=1?'s':''?>
          </div>
        </div>
      </div>

      <!-- ACTION BUTTONS + VIEW ITEMS BUTTON -->
      <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">

        <!-- View Items toggle -->
        <button class="view-items-btn" id="btn-<?=$o['order_id']?>"
          onclick="toggleItems(<?=$o['order_id']?>, this)">
          📋 View Items ▾
        </button>

        <!-- Status action buttons -->
        <?php if(!in_array($o['status'],['delivered','cancelled'])): ?>
          <?php if($o['status']==='pending'): ?>
            <button class="btn-ll btn-primary-ll btn-sm-ll" onclick="upd(<?=$o['order_id']?>,'confirmed')">✅ Confirm</button>
          <?php elseif($o['status']==='confirmed'): ?>
            <button class="btn-ll btn-secondary-ll btn-sm-ll" onclick="upd(<?=$o['order_id']?>,'preparing')">👨‍🍳 Preparing</button>
          <?php elseif($o['status']==='preparing'): ?>
            <button class="btn-ll btn-gold-ll btn-sm-ll" onclick="upd(<?=$o['order_id']?>,'out_for_delivery')">🚴 Out for Delivery</button>
          <?php elseif($o['status']==='out_for_delivery'): ?>
            <button class="btn-ll btn-sm-ll" style="background:rgba(17,153,142,0.12);color:var(--success);"
              onclick="upd(<?=$o['order_id']?>,'delivered')">🎉 Mark Delivered</button>
          <?php endif; ?>
          <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);"
            onclick="upd(<?=$o['order_id']?>,'cancelled')">❌ Cancel</button>
        <?php endif; ?>
      </div>

      <!-- ITEMS EXPANDABLE PANEL -->
      <div class="items-panel" id="items-<?=$o['order_id']?>">
        <div id="items-content-<?=$o['order_id']?>">
          <div class="spinner-sm"></div>
        </div>
      </div>

    </div>

    <?php endforeach; endif; ?>
  </div>
</div>

<div id="toast-container"></div>
<script>
// ── Toggle item list ──────────────────────────────────────
async function toggleItems(orderId, btn) {
  const panel = document.getElementById('items-' + orderId);
  const isOpen = panel.classList.contains('open');

  if (isOpen) {
    panel.classList.remove('open');
    btn.classList.remove('open');
    btn.innerHTML = '📋 View Items ▾';
    return;
  }

  panel.classList.add('open');
  btn.classList.add('open');
  btn.innerHTML = '📋 Hide Items ▴';

  // Only fetch once — if already populated skip
  const content = document.getElementById('items-content-' + orderId);
  if (content.dataset.loaded === '1') return;

  try {
    const res  = await fetch('api/order_items.php?order_id=' + orderId);
    const data = await res.json();

    if (!data.items || data.items.length === 0) {
      content.innerHTML = '<p style="color:var(--muted);font-size:0.88rem;text-align:center;padding:10px;">No items found for this order.</p>';
      content.dataset.loaded = '1';
      return;
    }

    const emojis = {
      tomato:'🍅', onion:'🧅', potato:'🥔', milk:'🥛', bread:'🍞',
      egg:'🥚', rice:'🍚', sugar:'🍬', tea:'🍵', coffee:'☕',
      banana:'🍌', apple:'🍎', mango:'🥭', chicken:'🍗', fish:'🐟',
      soap:'🧴', medicine:'💊', oil:'🫙'
    };
    function getEmoji(name) {
      const n = name.toLowerCase();
      for (const [k,v] of Object.entries(emojis)) {
        if (n.includes(k)) return v;
      }
      return '📦';
    }

    let html = data.items.map(item => `
      <div class="item-row">
        <div class="item-icon">${getEmoji(item.product_name)}</div>
        <div style="flex:1;">
          <div class="item-name">${esc(item.product_name)}</div>
          <div class="item-unit">per ${esc(item.unit || 'piece')}</div>
        </div>
        <div class="item-qty">× ${item.quantity}</div>
        <div class="item-price">₹${(item.price * item.quantity).toFixed(2)}</div>
      </div>
    `).join('');

    html += `
      <div class="items-total">
        <span style="font-size:0.88rem;font-weight:700;color:var(--muted);">
          ${data.items.length} item${data.items.length !== 1 ? 's' : ''} &nbsp;|&nbsp; Order Total
        </span>
        <span style="font-family:var(--font-heading);font-size:1.2rem;font-weight:900;color:var(--yellow);">
          ₹${parseFloat(data.total).toFixed(2)}
        </span>
      </div>
    `;

    content.innerHTML = html;
    content.dataset.loaded = '1';

  } catch(e) {
    content.innerHTML = '<div class="alert-ll danger">⚠️ Could not load items.</div>';
  }
}

// ── Update order status ───────────────────────────────────
async function upd(id, status) {
  const res  = await fetch('api/update_order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ order_id: id, status })
  });
  const data = await res.json();
  if (data.success) {
    showToast('Updated!', 'Status: ' + status.replace(/_/g,' '), 'success');
    setTimeout(() => location.reload(), 1200);
  } else {
    showToast('Error', data.message || 'Failed', 'error');
  }
}

// ── Helpers ───────────────────────────────────────────────
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
