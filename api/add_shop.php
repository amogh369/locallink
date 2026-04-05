<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['role']!=='shop_owner') { header('Location: dashboard.php'); exit; }
$userName = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Add Shop</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    #pick-map{height:260px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);border:3px solid white;cursor:crosshair;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div><div class="sidebar-user-info">Shop Owner</div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="shop_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="add_shop.php" class="active"><span class="nav-icon">➕</span> Add New Shop</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php"><span class="nav-icon">🛒</span> All Orders</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
  </nav>
  <div class="sidebar-footer"><button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button></div>
</div>
<nav class="navbar-ll">
  <div class="nav-left"><button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button><div class="nav-brand">Local<span>Link</span></div></div>
  <div class="nav-right"><div class="nav-avatar"><?=strtoupper(substr($userName,0,1))?></div></div>
</nav>
<div class="page-wrapper">
  <div class="page-hero" style="padding-bottom:80px;">
    <div style="position:relative;z-index:2;"><button class="back-btn" onclick="history.back()">← Back</button><div class="hero-title">Add Your <span>Shop</span> 🏪</div><div class="hero-subtitle">Register your shop and start receiving orders!</div></div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <div class="card-ll" style="padding:24px;max-width:600px;margin:0 auto;">
      <div id="add-alert" style="display:none;"></div>
      <form onsubmit="addShop(event)">
        <div class="form-group-ll"><label class="form-label-ll">Shop Name *</label><div class="input-group-ll"><span class="input-icon">🏪</span><input class="form-control-ll" type="text" id="shop-name" placeholder="e.g. Fresh Mart Grocery" required/></div></div>
        <div class="form-group-ll"><label class="form-label-ll">Category *</label>
          <select class="form-control-ll" id="shop-cat" required>
            <option value="">Select category</option>
            <option value="grocery">🛒 Grocery</option><option value="restaurant">🍽 Restaurant</option>
            <option value="pharmacy">💊 Pharmacy</option><option value="electronics">📱 Electronics</option>
            <option value="clothing">👗 Clothing</option><option value="plumbing">🔧 Plumbing</option>
            <option value="electrical">⚡ Electrical</option><option value="cleaning">🧹 Cleaning</option>
            <option value="other">🏪 Other</option>
          </select>
        </div>
        <div class="form-group-ll"><label class="form-label-ll">Description</label><textarea class="form-control-ll" id="shop-desc" rows="3" placeholder="Describe your shop..."></textarea></div>
        <div class="form-group-ll"><label class="form-label-ll">Phone Number</label><div class="input-group-ll"><span class="input-icon">📞</span><input class="form-control-ll" type="tel" id="shop-phone" placeholder="Shop contact number" maxlength="10"/></div></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="form-group-ll"><label class="form-label-ll">Opening Time</label><input class="form-control-ll" type="time" id="shop-open" value="08:00"/></div>
          <div class="form-group-ll"><label class="form-label-ll">Closing Time</label><input class="form-control-ll" type="time" id="shop-close" value="22:00"/></div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">📍 Shop Location (click map to pin) *</label>
          <div id="pick-map"></div>
          <div style="font-size:0.82rem;color:var(--muted);margin-top:6px;">🖱 Click on the map to set your exact shop location</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
            <div><label class="form-label-ll">Latitude</label><input class="form-control-ll" type="text" id="shop-lat" placeholder="Auto-filled" readonly/></div>
            <div><label class="form-label-ll">Longitude</label><input class="form-control-ll" type="text" id="shop-lng" placeholder="Auto-filled" readonly/></div>
          </div>
          <button type="button" class="btn-ll btn-outline-ll btn-sm-ll" style="margin-top:8px;" onclick="useMyLocation()">📍 Use My Current Location</button>
        </div>
        <div class="form-group-ll"><label class="form-label-ll">Address</label><div class="input-group-ll"><span class="input-icon">📍</span><input class="form-control-ll" type="text" id="shop-address" placeholder="Full address of your shop"/></div></div>
        <button type="submit" class="btn-ll btn-primary-ll btn-full" id="add-btn">🏪 Register Shop</button>
      </form>
    </div>
  </div>
</div>
<div id="toast-container"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let pickMap,pickMarker,pickedLat=null,pickedLng=null;
document.addEventListener('DOMContentLoaded',()=>{pickMap=L.map('pick-map');L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(pickMap);pickMap.setView([12.9716,77.5946],13);pickMap.on('click',e=>setPin(e.latlng.lat,e.latlng.lng));useMyLocation();});
function setPin(lat,lng){pickedLat=lat.toFixed(7);pickedLng=lng.toFixed(7);document.getElementById('shop-lat').value=pickedLat;document.getElementById('shop-lng').value=pickedLng;if(pickMarker)pickMap.removeLayer(pickMarker);pickMarker=L.marker([lat,lng]).addTo(pickMap).bindPopup('<b>Your Shop Location</b>').openPopup();fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`).then(r=>r.json()).then(d=>{const a=d.display_name||'';document.getElementById('shop-address').value=a.split(',').slice(0,4).join(',');}).catch(()=>{});}
function useMyLocation(){navigator.geolocation.getCurrentPosition(pos=>{pickMap.setView([pos.coords.latitude,pos.coords.longitude],15);setPin(pos.coords.latitude,pos.coords.longitude);});}
async function addShop(e){e.preventDefault();if(!pickedLat||!pickedLng){showAlert('Please select shop location on the map.','danger');return;}const btn=document.getElementById('add-btn');btn.innerHTML='⏳ Registering...';btn.disabled=true;const res=await fetch('api/add_shop.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:document.getElementById('shop-name').value,category:document.getElementById('shop-cat').value,description:document.getElementById('shop-desc').value,phone:document.getElementById('shop-phone').value,opening_time:document.getElementById('shop-open').value,closing_time:document.getElementById('shop-close').value,latitude:pickedLat,longitude:pickedLng,address:document.getElementById('shop-address').value})});const data=await res.json();if(data.success){showAlert('Shop registered! 🎉','success');setTimeout(()=>location.href='shop_dashboard.php',1500);}else{showAlert(data.message||'Failed','danger');btn.innerHTML='🏪 Register Shop';btn.disabled=false;}}
function showAlert(msg,type){const el=document.getElementById('add-alert');el.className='alert-ll '+(type==='success'?'success':'danger');el.innerHTML=(type==='success'?'✅ ':'⚠️ ')+msg;el.style.display='flex';}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
