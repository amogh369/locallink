<?php
require_once 'config.php';
requireLogin();
$shopId = intval($_GET['id'] ?? 0);
if (!$shopId) { header('Location: dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT s.*,u.name AS owner_name,u.phone AS owner_phone FROM shops s JOIN users u ON s.owner_id=u.user_id WHERE s.shop_id=?");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();
if (!$shop) { header('Location: dashboard.php'); exit; }

$ps = $pdo->prepare("SELECT * FROM products WHERE shop_id=? AND is_available=1 ORDER BY name");
$ps->execute([$shopId]); $products = $ps->fetchAll();

$ss = $pdo->prepare("SELECT * FROM services WHERE shop_id=? AND is_available=1 ORDER BY name");
$ss->execute([$shopId]); $services = $ss->fetchAll();

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount = (int)$nc->fetchColumn();

function productEmoji($n){$n=strtolower($n);$m=['tomato'=>'🍅','onion'=>'🧅','potato'=>'🥔','milk'=>'🥛','bread'=>'🍞','egg'=>'🥚','rice'=>'🍚','sugar'=>'🍬','tea'=>'🍵','coffee'=>'☕','banana'=>'🍌','apple'=>'🍎','mango'=>'🥭','chicken'=>'🍗','fish'=>'🐟','soap'=>'🧴','medicine'=>'💊'];foreach($m as $k=>$v){if(str_contains($n,$k))return $v;}return '📦';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – <?=htmlspecialchars($shop['name'])?></title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .tabs-nav{display:flex;border-bottom:2px solid #f0f0f0;margin-bottom:20px;}
    .tab-btn{flex:1;padding:12px;text-align:center;cursor:pointer;font-weight:700;font-size:0.9rem;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-2px;transition:var(--transition);background:none;border-top:none;border-left:none;border-right:none;font-family:var(--font-body);}
    .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary);}
    .tab-pane{display:none;}.tab-pane.active{display:block;}
    .products-list{display:flex;flex-direction:column;gap:12px;}
    .time-slots{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px;}
    .time-slot{padding:10px 6px;border-radius:var(--radius-md);text-align:center;font-size:0.82rem;font-weight:700;cursor:pointer;border:2px solid #e8e8f0;transition:var(--transition);}
    .time-slot:hover{border-color:var(--primary);color:var(--primary);}
    .time-slot.selected{background:var(--primary);color:white;border-color:var(--primary);}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php"><span class="nav-icon">📦</span> My Orders</a>
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
      <div class="hero-title"><?=htmlspecialchars($shop['name'])?></div>
      <div class="hero-subtitle">📍 <?=htmlspecialchars($shop['address']??'')?> &nbsp;|&nbsp; ⭐ <?=number_format($shop['rating'],1)?> &nbsp;|&nbsp; <?=$shop['is_open']?'🟢 Open':'🔴 Closed'?></div>
      <div class="hero-subtitle" style="margin-top:6px;">📞 <?=htmlspecialchars($shop['phone']??'N/A')?> &nbsp;|&nbsp; 🕐 <?=date('g:i A',strtotime($shop['opening_time']))?> – <?=date('g:i A',strtotime($shop['closing_time']))?></div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">
    <div id="shop-alert" style="display:none;"></div>
    <div class="card-ll" style="padding:0;overflow:hidden;margin-bottom:20px;">
      <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('products',this)">🛒 Products (<?=count($products)?>)</button>
        <button class="tab-btn" onclick="switchTab('services',this)">🔧 Services (<?=count($services)?>)</button>
        <button class="tab-btn" onclick="switchTab('reviews',this)">⭐ Reviews</button>
      </div>
      <div style="padding:16px;">
        <div class="tab-pane active" id="tab-products">
          <?php if(empty($products)):?>
            <div class="empty-state"><div class="empty-icon">📦</div><h3>No products listed</h3></div>
          <?php else:?>
          <div class="products-list">
            <?php foreach($products as $p):?>
            <div class="card-ll product-card" id="prod-<?=$p['product_id']?>">
              <div class="product-icon"><?=productEmoji($p['name'])?></div>
              <div class="product-info">
                <div class="product-name"><?=htmlspecialchars($p['name'])?></div>
                <div class="product-unit">per <?=htmlspecialchars($p['unit']??'piece')?></div>
                <div class="product-price">₹<?=number_format($p['price'],2)?></div>
              </div>
              <div class="qty-control">
                <button class="qty-btn minus" onclick="changeQty(<?=$p['product_id']?>,<?=$p['price']?>,'<?=addslashes($p['name'])?>',false)">−</button>
                <span class="qty-num" id="qty-<?=$p['product_id']?>">0</span>
                <button class="qty-btn plus" onclick="changeQty(<?=$p['product_id']?>,<?=$p['price']?>,'<?=addslashes($p['name'])?>',true)">+</button>
              </div>
            </div>
            <?php endforeach;?>
          </div>
          <?php endif;?>
        </div>

        <div class="tab-pane" id="tab-services">
          <?php if(empty($services)):?>
            <div class="empty-state"><div class="empty-icon">🔧</div><h3>No services listed</h3></div>
          <?php else:?>
          <div class="products-list">
            <?php foreach($services as $sv):?>
            <div class="card-ll" style="padding:16px;">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                  <div style="font-weight:800;font-size:0.95rem;margin-bottom:4px;"><?=htmlspecialchars($sv['name'])?></div>
                  <div style="font-size:0.82rem;color:var(--muted);"><?=htmlspecialchars($sv['description']??'')?></div>
                  <div style="margin-top:6px;"><span class="status-badge confirmed">⏱ <?=$sv['duration_mins']?> mins</span></div>
                </div>
                <div style="text-align:right;">
                  <div style="font-size:1.2rem;font-weight:900;color:var(--primary);">₹<?=number_format($sv['price'],2)?></div>
                  <button class="btn-ll btn-primary-ll btn-sm-ll" style="margin-top:8px;" onclick="bookService(<?=$sv['service_id']?>,<?=$shopId?>,'<?=addslashes($sv['name'])?>',<?=$sv['price']?>)">Book Now</button>
                </div>
              </div>
            </div>
            <?php endforeach;?>
          </div>
          <?php endif;?>
        </div>

        <div class="tab-pane" id="tab-reviews">
          <div class="empty-state"><div class="empty-icon">⭐</div><h3>No reviews yet</h3><p>Be the first to review!</p></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="cart-float" id="cart-float" style="display:none;" onclick="placeOrder()">
  <div class="cart-float-info">
    <div class="cart-float-count" id="cart-count-label">0 items</div>
    <div class="cart-float-total" id="cart-total-label">₹0.00</div>
  </div>
  <div class="cart-float-action">Place Order →</div>
</div>

<div id="booking-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div class="card-ll" style="width:100%;max-width:420px;padding:28px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:900;">Book Service</h3>
      <button onclick="closeBooking()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;">✕</button>
    </div>
    <div id="booking-service-info" style="margin-bottom:20px;"></div>
    <div class="form-group-ll"><label class="form-label-ll">Select Date</label><input type="date" id="booking-date" class="form-control-ll"/></div>
    <div class="form-group-ll"><label class="form-label-ll">Select Time Slot</label><div class="time-slots" id="time-slots"></div></div>
    <div class="form-group-ll"><label class="form-label-ll">Notes (optional)</label><textarea class="form-control-ll" id="booking-notes" rows="3" placeholder="Any special instructions..."></textarea></div>
    <button class="btn-ll btn-primary-ll btn-full" onclick="confirmBooking()">Confirm Booking</button>
  </div>
</div>

<div id="toast-container"></div>
<script>
const SHOP_ID=<?=$shopId?>;
let cart=JSON.parse(localStorage.getItem('ll_cart_'+SHOP_ID)||'{}');
let selectedService=null,selectedSlot=null;

function changeQty(pid,price,name,add){if(!cart[pid])cart[pid]={id:pid,name,price,qty:0};if(add)cart[pid].qty++;else{cart[pid].qty=Math.max(0,cart[pid].qty-1);if(!cart[pid].qty)delete cart[pid];}document.getElementById('qty-'+pid).textContent=cart[pid]?.qty||0;localStorage.setItem('ll_cart_'+SHOP_ID,JSON.stringify(cart));updateCartFloat();}

function updateCartFloat(){const items=Object.values(cart);const total=items.reduce((s,i)=>s+i.price*i.qty,0);const count=items.reduce((s,i)=>s+i.qty,0);const f=document.getElementById('cart-float');if(count>0){f.style.display='flex';document.getElementById('cart-count-label').textContent=count+' item'+(count>1?'s':'');document.getElementById('cart-total-label').textContent='₹'+total.toFixed(2);}else f.style.display='none';}

async function placeOrder(){if(!Object.keys(cart).length)return;const res=await fetch('api/place_order.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({shop_id:SHOP_ID,cart:Object.values(cart),delivery_address:'Current Location'})});const data=await res.json();if(data.success){localStorage.removeItem('ll_cart_'+SHOP_ID);cart={};updateCartFloat();showToast('Order Placed! 🎉',`Order #${data.order_id} confirmed!`,'success');setTimeout(()=>window.location.href='orders.php',1800);}else showToast('Error',data.message||'Order failed','error');}

function switchTab(name,btn){document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('tab-'+name).classList.add('active');btn.classList.add('active');}

function bookService(sid,shopId,name,price){selectedService={sid,shopId,name,price};document.getElementById('booking-service-info').innerHTML=`<div class="alert-ll info">📋 <strong>${esc(name)}</strong> &nbsp;|&nbsp; ₹${price.toFixed(2)}</div>`;const today=new Date().toISOString().split('T')[0];document.getElementById('booking-date').min=today;document.getElementById('booking-date').value=today;const slots=['9:00 AM','10:00 AM','11:00 AM','12:00 PM','2:00 PM','3:00 PM','4:00 PM','5:00 PM'];document.getElementById('time-slots').innerHTML=slots.map(s=>`<div class="time-slot" onclick="selectSlot(this,'${s}')">${s}</div>`).join('');document.getElementById('booking-modal').style.display='flex';}
function selectSlot(el,slot){document.querySelectorAll('.time-slot').forEach(t=>t.classList.remove('selected'));el.classList.add('selected');selectedSlot=slot;}
function closeBooking(){document.getElementById('booking-modal').style.display='none';}
async function confirmBooking(){if(!selectedSlot){showToast('','Please select a time slot','warning');return;}const date=document.getElementById('booking-date').value;const notes=document.getElementById('booking-notes').value;const res=await fetch('api/book_service.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({service_id:selectedService.sid,shop_id:selectedService.shopId,booking_date:date,time_slot:selectedSlot,notes})});const data=await res.json();closeBooking();if(data.success){showToast('Booking Confirmed! 📅',`Slot: ${selectedSlot} on ${date}`,'success');setTimeout(()=>location.href='bookings.php',1800);}else showToast('Error',data.message||'Booking failed','error');}

function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}

document.addEventListener('DOMContentLoaded',()=>{Object.entries(cart).forEach(([id,item])=>{const el=document.getElementById('qty-'+id);if(el)el.textContent=item.qty;});updateCartFloat();});
</script>
</body>
</html>
