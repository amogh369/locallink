<?php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount=(int)$nc->fetchColumn();

$stmt = $pdo->prepare("
    SELECT o.*,s.name AS shop_name,s.category,COUNT(oi.item_id) AS item_count
    FROM orders o
    JOIN shops s ON o.shop_id=s.shop_id
    LEFT JOIN order_items oi ON o.order_id=oi.order_id
    WHERE o.user_id=?
    GROUP BY o.order_id,o.user_id,o.shop_id,o.total_amount,o.status,o.delivery_address,o.delivery_lat,o.delivery_lng,o.notes,o.payment_method,o.payment_status,o.created_at,o.updated_at,s.name,s.category
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]); $orders=$stmt->fetchAll();
$STEPS=['pending'=>0,'confirmed'=>1,'preparing'=>2,'out_for_delivery'=>3,'delivered'=>4];
$LABELS=['Placed','Confirmed','Preparing','On the way','Delivered'];
$ICONS =['📝','✅','👨‍🍳','🚴','🎉'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – My Orders</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .order-items-list{display:none;}.order-items-list.show{display:block;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php" class="active"><span class="nav-icon">📦</span> My Orders</a>
    <a href="bookings.php"><span class="nav-icon">📅</span> My Bookings</a>
    <a href="profile.php"><span class="nav-icon">👤</span> My Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications</a>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>
<nav class="navbar-ll">
  <div class="nav-left"><button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button><div class="nav-brand">Local<span>Link</span></div></div>
  <div class="nav-right">
    <button class="nav-notif" onclick="location.href='notifications.php'">🔔<?php if($notifCount>0):?><span class="notif-badge"><?=$notifCount?></span><?php endif;?></button>
    <div class="nav-avatar" onclick="location.href='profile.php'"><?=strtoupper(substr($userName,0,1))?></div>
  </div>
</nav>

<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;">
      <button class="back-btn" onclick="history.back()">← Back</button>
      <div class="hero-title">My <span>Orders</span> 📦</div>
      <div class="hero-subtitle"><?=count($orders)?> order<?=count($orders)!=1?'s':''?> placed</div>
    </div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <?php if(empty($orders)):?>
      <div class="empty-state"><div class="empty-icon">📭</div><h3>No orders yet!</h3><p>Explore nearby shops and place your first order.</p><a href="dashboard.php" class="btn-ll btn-primary-ll" style="margin-top:16px;">🛒 Browse Shops</a></div>
    <?php else: foreach($orders as $o):
      $stepIdx=$STEPS[$o['status']]??0;
      $isCancelled=$o['status']==='cancelled';
    ?>
    <div class="card-ll" style="margin-bottom:16px;">
      <div style="padding:18px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-family:var(--font-heading);font-weight:800;font-size:1rem;">🏪 <?=htmlspecialchars($o['shop_name'])?></div>
            <div style="font-size:0.8rem;color:var(--muted);margin-top:3px;">Order #<?=$o['order_id']?> &nbsp;|&nbsp; <?=date('d M Y, h:i A',strtotime($o['created_at']))?></div>
            <div style="font-size:0.82rem;margin-top:2px;">📦 <?=$o['item_count']?> item<?=$o['item_count']!=1?'s':''?> &nbsp;|&nbsp; 💳 <?=ucfirst($o['payment_method']??'cash')?></div>
          </div>
          <div style="text-align:right;">
            <div style="font-family:var(--font-heading);font-size:1.3rem;font-weight:900;color:var(--primary);">₹<?=number_format($o['total_amount'],2)?></div>
            <span class="status-badge <?=$o['status']?>" style="margin-top:6px;display:inline-flex;"><?=$isCancelled?'❌':($stepIdx==4?'✅':'🔄')?> <?=ucwords(str_replace('_',' ',$o['status']))?></span>
          </div>
        </div>

        <?php if(!$isCancelled):?>
        <div style="margin-top:16px;">
          <div class="order-stepper">
            <?php for($i=0;$i<count($LABELS);$i++):
              $cls=$i<$stepIdx?'done':($i===$stepIdx?'active':'');?>
            <div class="step-item <?=$cls?>">
              <div class="step-dot"><?=$i<$stepIdx?'✓':$ICONS[$i]?></div>
              <div class="step-label"><?=$LABELS[$i]?></div>
            </div>
            <?php endfor;?>
          </div>
        </div>
        <?php endif;?>

        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn-ll btn-outline-ll btn-sm-ll" onclick="toggleItems(<?=$o['order_id']?>)">View Items ▾</button>
          <?php if($o['status']==='pending'):?>
          <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="cancelOrder(<?=$o['order_id']?>)">❌ Cancel</button>
          <?php endif;?>
        </div>

        <div class="order-items-list" id="items-<?=$o['order_id']?>">
          <div style="margin-top:14px;padding:14px;background:var(--light);border-radius:var(--radius-md);" id="items-content-<?=$o['order_id']?>">
            <div class="spinner-ll" style="width:30px;height:30px;margin:10px auto;"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; endif;?>
  </div>
</div>
<div id="toast-container"></div>
<script>
function toggleItems(id){const el=document.getElementById('items-'+id);el.classList.toggle('show');if(el.classList.contains('show')){fetch('api/order_items.php?order_id='+id).then(r=>r.json()).then(data=>{const c=document.getElementById('items-content-'+id);if(data.items&&data.items.length){c.innerHTML=data.items.map(i=>`<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0;"><span style="font-size:0.88rem;font-weight:600;">${esc(i.product_name)} × ${i.quantity}</span><span style="font-weight:700;color:var(--primary);">₹${(i.price*i.quantity).toFixed(2)}</span></div>`).join('')+`<div style="text-align:right;font-weight:800;padding-top:8px;">Total: ₹${data.total?.toFixed(2)||'—'}</div>`;}else c.innerHTML='<p style="color:var(--muted)">No items found.</p>';});}}
async function cancelOrder(id){if(!confirm('Cancel this order?'))return;const res=await fetch('api/update_order.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:id,status:'cancelled'})});const data=await res.json();if(data.success){showToast('Order Cancelled','','warning');setTimeout(()=>location.reload(),1500);}else showToast('Error',data.message||'Failed','error');}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
