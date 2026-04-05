<?php
// notifications.php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$isOwner  = $_SESSION['role'] === 'shop_owner';
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$userId]); $notifications=$notifs->fetchAll();
$typeIcons=['order'=>'📦','booking'=>'📅','promo'=>'🎁','system'=>'⚙️'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Notifications</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .notif-item{display:flex;gap:14px;padding:16px;border-bottom:1px solid var(--border);align-items:flex-start;}
    .notif-item:last-child{border-bottom:none;}
    .notif-icon-circle{width:44px;height:44px;border-radius:50%;background:var(--gradient-main);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="<?=$isOwner?'shop_dashboard.php':'dashboard.php'?>"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php"><span class="nav-icon">📦</span> My Orders</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php" class="active"><span class="nav-icon">🔔</span> Notifications</a>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>
<nav class="navbar-ll">
  <div class="nav-left"><button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button><div class="nav-brand">Local<span>Link</span></div></div>
  <div class="nav-right"><div class="nav-avatar"><?=strtoupper(substr($userName,0,1))?></div></div>
</nav>
<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;">
      <button class="back-btn" onclick="history.back()">← Back</button>
      <div class="hero-title">Notifications <span>🔔</span></div>
      <div class="hero-subtitle"><?=count($notifications)?> notification<?=count($notifications)!=1?'s':''?></div>
    </div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <div class="card-ll" style="padding:0;overflow:hidden;">
      <?php if(empty($notifications)):?>
        <div class="empty-state" style="padding:50px;"><div class="empty-icon">🔕</div><h3>No notifications yet</h3><p>Order something to get started!</p></div>
      <?php else: foreach($notifications as $n):?>
      <div class="notif-item">
        <div class="notif-icon-circle"><?=$typeIcons[$n['type']]??'🔔'?></div>
        <div style="flex:1;">
          <div style="font-weight:800;font-size:0.92rem;"><?=htmlspecialchars($n['title'])?></div>
          <div style="font-size:0.85rem;color:#444;margin-top:3px;"><?=htmlspecialchars($n['message'])?></div>
          <div style="font-size:0.75rem;color:var(--muted);margin-top:5px;"><?=date('d M Y, h:i A',strtotime($n['created_at']))?></div>
        </div>
        <?php if(!$n['is_read']):?><div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:6px;flex-shrink:0;"></div><?php endif;?>
      </div>
      <?php endforeach; endif;?>
    </div>
  </div>
</div>
<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
