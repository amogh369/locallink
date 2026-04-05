<?php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$isOwner  = $_SESSION['role'] === 'shop_owner';
$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount=(int)$nc->fetchColumn();
$stmt = $pdo->prepare("
    SELECT b.*,s.name AS shop_name,s.address AS shop_address,sv.name AS service_name,sv.price
    FROM bookings b JOIN shops s ON b.shop_id=s.shop_id JOIN services sv ON b.service_id=sv.service_id
    WHERE b.user_id=? ORDER BY b.created_at DESC
");
$stmt->execute([$userId]); $bookings=$stmt->fetchAll();
$SC=['pending'=>'pending','confirmed'=>'confirmed','completed'=>'delivered','cancelled'=>'cancelled'];
$SI=['pending'=>'⏳','confirmed'=>'✅','completed'=>'🎉','cancelled'=>'❌'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – My Bookings</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .cancel-confirm{display:none;background:rgba(235,51,73,0.06);border-radius:var(--radius-md);padding:14px;margin-top:12px;}
    .cancel-confirm.show{display:block;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div><div class="sidebar-user-info"><?=$isOwner?'Shop Owner':'Customer'?></div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php"><span class="nav-icon">📦</span> My Orders</a>
    <a href="bookings.php" class="active"><span class="nav-icon">📅</span> My Bookings</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications
      <?php if($notifCount>0):?><span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span><?php endif;?>
    </a>
    <?php if($isOwner):?><div class="sidebar-divider"></div><a href="shop_dashboard.php"><span class="nav-icon">📊</span> Shop Dashboard</a><?php endif;?>
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
      <div class="hero-title">My <span>Bookings</span> 📅</div>
      <div class="hero-subtitle"><?=count($bookings)?> booking<?=count($bookings)!=1?'s':''?></div>
    </div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <?php if(empty($bookings)):?>
      <div class="empty-state"><div class="empty-icon">📭</div><h3>No bookings yet!</h3><p>Book a service from nearby shops.</p><a href="dashboard.php" class="btn-ll btn-primary-ll" style="margin-top:16px;display:inline-flex;">🔧 Browse Services</a></div>
    <?php else: foreach($bookings as $b):
      $sc=$SC[$b['status']]??'pending'; $si=$SI[$b['status']]??'⏳';
    ?>
    <div class="card-ll" style="margin-bottom:16px;">
      <div style="padding:18px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-family:var(--font-heading);font-weight:800;font-size:1rem;">🔧 <?=htmlspecialchars($b['service_name'])?></div>
            <div style="font-size:0.82rem;color:var(--muted);margin-top:3px;">🏪 <?=htmlspecialchars($b['shop_name'])?></div>
            <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
              <div style="background:rgba(71,118,230,0.1);color:var(--secondary);border-radius:var(--radius-md);padding:5px 12px;font-size:0.82rem;font-weight:700;">📅 <?=date('d M Y',strtotime($b['booking_date']))?></div>
              <div style="background:rgba(247,151,30,0.1);color:var(--warning);border-radius:var(--radius-md);padding:5px 12px;font-size:0.82rem;font-weight:700;">🕐 <?=htmlspecialchars($b['time_slot'])?></div>
            </div>
            <?php if($b['notes']):?><div style="font-size:0.8rem;color:var(--muted);margin-top:6px;">📝 <?=htmlspecialchars($b['notes'])?></div><?php endif;?>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-family:var(--font-heading);font-size:1.3rem;font-weight:900;color:var(--primary);">₹<?=number_format($b['price'],2)?></div>
            <span class="status-badge <?=$sc?>" style="margin-top:8px;display:inline-flex;"><?=$si?> <?=ucfirst($b['status'])?></span>
          </div>
        </div>
        <?php if($b['status']==='pending'):?>
        <div style="margin-top:12px;">
          <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="showCancel(<?=$b['booking_id']?>)">❌ Cancel Booking</button>
        </div>
        <div class="cancel-confirm" id="cancel-<?=$b['booking_id']?>">
          <div style="font-weight:700;margin-bottom:10px;">Are you sure you want to cancel?</div>
          <div style="display:flex;gap:8px;">
            <button class="btn-ll btn-primary-ll btn-sm-ll" onclick="cancelBooking(<?=$b['booking_id']?>)">Yes, Cancel</button>
            <button class="btn-ll btn-outline-ll btn-sm-ll" onclick="hideCancel(<?=$b['booking_id']?>)">Keep It</button>
          </div>
        </div>
        <?php endif;?>
      </div>
    </div>
    <?php endforeach; endif;?>
  </div>
</div>
<div id="toast-container"></div>
<script>
function showCancel(id){document.getElementById('cancel-'+id).classList.add('show');}
function hideCancel(id){document.getElementById('cancel-'+id).classList.remove('show');}
async function cancelBooking(id){const res=await fetch('api/cancel_booking.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({booking_id:id})});const data=await res.json();if(data.success){showToast('Booking Cancelled','','warning');setTimeout(()=>location.reload(),1200);}else showToast('Error',data.message||'Failed','error');}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
