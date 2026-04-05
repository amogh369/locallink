<?php
require_once 'config.php';
requireLogin();
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$userId]); $user=$stmt->fetch();
$userName = $user['name']; $isOwner = $user['role']==='shop_owner';
$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount=(int)$nc->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Profile</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .profile-avatar{width:90px;height:90px;border-radius:50%;background:var(--gradient-main);display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:white;font-weight:900;margin:0 auto 14px;border:4px solid white;box-shadow:var(--shadow-md);}
    .upgrade-card{background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:var(--radius-xl);padding:24px;color:white;margin-bottom:20px;max-width:500px;margin-left:auto;margin-right:auto;position:relative;overflow:hidden;}
    .upgrade-card::before{content:'🏪';position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:5rem;opacity:0.12;}
    .owner-card{background:linear-gradient(135deg,#0f3460,#16213e);border-radius:var(--radius-xl);padding:24px;color:white;margin-bottom:20px;max-width:500px;margin-left:auto;margin-right:auto;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div><div class="sidebar-user-info"><?=$isOwner?'Shop Owner':'Customer'?></div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Browse Shops</a>
    <a href="orders.php"><span class="nav-icon">📦</span> My Orders</a>
    <a href="bookings.php"><span class="nav-icon">📅</span> My Bookings</a>
    <a href="profile.php" class="active"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications</a>
    <?php if($isOwner):?>
    <div class="sidebar-divider"></div>
    <a href="shop_dashboard.php"><span class="nav-icon">📊</span> Shop Dashboard</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php"><span class="nav-icon">🛒</span> Shop Orders</a>
    <?php endif;?>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>
<nav class="navbar-ll">
  <div class="nav-left"><button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button><div class="nav-brand">Local<span>Link</span></div></div>
  <div class="nav-right">
    <button class="nav-notif" onclick="location.href='notifications.php'">🔔<?php if($notifCount>0):?><span class="notif-badge"><?=$notifCount?></span><?php endif;?></button>
    <div class="nav-avatar"><?=strtoupper(substr($userName,0,1))?></div>
  </div>
</nav>
<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;"><button class="back-btn" onclick="history.back()">← Back</button><div class="hero-title">My <span>Profile</span> 👤</div></div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <?php if(!$isOwner):?>
    <div class="upgrade-card">
      <div style="font-family:var(--font-heading);font-size:1.3rem;font-weight:900;margin-bottom:6px;">Want to sell on LocalLink? 🚀</div>
      <div style="font-size:0.88rem;color:rgba(255,255,255,0.8);margin-bottom:16px;line-height:1.6;">Upgrade to <strong>Shop Owner</strong> for free. You can still order from other shops too!</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
        <span style="background:rgba(255,255,255,0.1);border-radius:20px;padding:5px 12px;font-size:0.8rem;">✅ List your shop</span>
        <span style="background:rgba(255,255,255,0.1);border-radius:20px;padding:5px 12px;font-size:0.8rem;">✅ Receive orders</span>
        <span style="background:rgba(255,255,255,0.1);border-radius:20px;padding:5px 12px;font-size:0.8rem;">✅ Order from others</span>
      </div>
      <button class="btn-ll btn-gold-ll" onclick="becomeOwner()" id="upgrade-btn" style="font-weight:900;">🏪 Become a Shop Owner — Free!</button>
    </div>
    <?php else:?>
    <div class="owner-card">
      <div style="font-family:var(--font-heading);font-size:1.2rem;font-weight:900;margin-bottom:6px;">You have dual access! 🎯</div>
      <div style="font-size:0.85rem;color:rgba(255,255,255,0.8);margin-bottom:16px;">As a shop owner you can also browse and order from any other shop.</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn-ll btn-primary-ll" onclick="location.href='dashboard.php'">🛒 Shop as Customer</button>
        <button class="btn-ll" style="background:rgba(255,200,55,0.2);color:var(--accent);border:2px solid rgba(255,200,55,0.5);" onclick="location.href='shop_dashboard.php'">📊 Manage My Shops</button>
      </div>
    </div>
    <?php endif;?>

    <div class="card-ll" style="padding:28px;max-width:500px;margin:0 auto 20px;">
      <div style="text-align:center;margin-bottom:24px;">
        <div class="profile-avatar"><?=strtoupper(substr($userName,0,1))?></div>
        <div style="font-family:var(--font-heading);font-size:1.4rem;font-weight:900;"><?=htmlspecialchars($userName)?></div>
        <span class="status-badge <?=$isOwner?'confirmed':'delivered'?>" style="margin-top:8px;display:inline-flex;"><?=$isOwner?'🏪 Shop Owner':'🛒 Customer'?></span>
      </div>
      <div id="profile-alert" style="display:none;"></div>
      <form onsubmit="updateProfile(event)">
        <div class="form-group-ll"><label class="form-label-ll">Full Name</label><div class="input-group-ll"><span class="input-icon">👤</span><input class="form-control-ll" type="text" id="p-name" value="<?=htmlspecialchars($userName)?>" required/></div></div>
        <div class="form-group-ll"><label class="form-label-ll">Email (cannot change)</label><div class="input-group-ll"><span class="input-icon">📧</span><input class="form-control-ll" value="<?=htmlspecialchars($user['email'])?>" readonly style="background:#f9f9f9;cursor:not-allowed;"/></div></div>
        <div class="form-group-ll"><label class="form-label-ll">Phone</label><div class="input-group-ll"><span class="input-icon">📱</span><input class="form-control-ll" type="tel" id="p-phone" value="<?=htmlspecialchars($user['phone']??'')?>" maxlength="10"/></div></div>
        <div class="form-group-ll"><label class="form-label-ll">Address</label><div class="input-group-ll"><span class="input-icon">📍</span><input class="form-control-ll" type="text" id="p-address" value="<?=htmlspecialchars($user['address']??'')?>"/></div></div>
        <button type="submit" class="btn-ll btn-primary-ll btn-full" id="profile-btn">💾 Update Profile</button>
      </form>
    </div>

    <div class="card-ll" style="padding:24px;max-width:500px;margin:0 auto 20px;">
      <div class="section-title"><span class="section-title-dot">🔒</span> Change Password</div>
      <form onsubmit="changePassword(event)">
        <div class="form-group-ll"><label class="form-label-ll">Current Password</label><div class="input-group-ll"><span class="input-icon">🔒</span><input class="form-control-ll" type="password" id="cur-pwd" placeholder="Current password"/></div></div>
        <div class="form-group-ll"><label class="form-label-ll">New Password</label><div class="input-group-ll"><span class="input-icon">🔑</span><input class="form-control-ll" type="password" id="new-pwd" placeholder="Min 6 characters" minlength="6"/></div></div>
        <button type="submit" class="btn-ll btn-secondary-ll btn-full">🔒 Change Password</button>
      </form>
    </div>
    <div style="max-width:500px;margin:0 auto 40px;">
      <button class="btn-ll btn-full" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="logout()">🚪 Sign Out</button>
    </div>
  </div>
</div>
<div id="toast-container"></div>
<script>
async function becomeOwner(){const btn=document.getElementById('upgrade-btn');btn.innerHTML='⏳ Upgrading...';btn.disabled=true;const res=await fetch('api/upgrade_role.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'become_owner'})});const data=await res.json();if(data.success){showToast('🎉 You are now a Shop Owner!','Redirecting...','success');setTimeout(()=>window.location.href=data.redirect||'shop_dashboard.php',1500);}else{showToast('Error',data.message||'Failed','error');btn.innerHTML='🏪 Become a Shop Owner — Free!';btn.disabled=false;}}
async function updateProfile(e){e.preventDefault();const btn=document.getElementById('profile-btn');btn.innerHTML='⏳...';btn.disabled=true;const res=await fetch('api/update_profile.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:document.getElementById('p-name').value,phone:document.getElementById('p-phone').value,address:document.getElementById('p-address').value})});const data=await res.json();showProfileAlert(data.message,data.success?'success':'danger');btn.innerHTML='💾 Update Profile';btn.disabled=false;}
async function changePassword(e){e.preventDefault();const res=await fetch('api/change_password.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current:document.getElementById('cur-pwd').value,newpwd:document.getElementById('new-pwd').value})});const data=await res.json();showToast(data.success?'Password Changed':'Error',data.message,data.success?'success':'error');if(data.success){document.getElementById('cur-pwd').value='';document.getElementById('new-pwd').value='';}}
function showProfileAlert(msg,type){const el=document.getElementById('profile-alert');el.className='alert-ll '+(type==='success'?'success':'danger');el.innerHTML=(type==='success'?'✅ ':'⚠️ ')+msg;el.style.display='flex';setTimeout(()=>el.style.display='none',4000);}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
