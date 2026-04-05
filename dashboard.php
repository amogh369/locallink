<?php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];
// Shop owners can also browse — no redirect

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0");
$stmt->execute([$userId]);
$notifCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Discover Nearby Shops</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    #map{height:300px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);border:3px solid white;}
    .shops-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;}
    .search-section{background:white;border-radius:var(--radius-lg);padding:18px;box-shadow:var(--shadow-sm);margin-bottom:20px;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Local<span>Link</span></div>
    <div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div>
    <div class="sidebar-user-info"><?=$userRole==='shop_owner'?'Shop Owner':'Customer'?></div></div>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="active"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php"><span class="nav-icon">📦</span> My Orders</a>
    <a href="bookings.php"><span class="nav-icon">📅</span> My Bookings</a>
    <a href="profile.php"><span class="nav-icon">👤</span> My Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications
      <?php if($notifCount>0):?><span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span><?php endif;?>
    </a>
    <?php if($userRole==='shop_owner'):?>
    <div class="sidebar-divider"></div>
    <a href="shop_dashboard.php"><span class="nav-icon">📊</span> Shop Dashboard</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php"><span class="nav-icon">🛒</span> Shop Orders</a>
    <a href="add_shop.php"><span class="nav-icon">➕</span> Add New Shop</a>
    <?php else:?>
    <div class="sidebar-divider"></div>
    <a href="nearby_services.php"><span class="nav-icon">🔧</span> Book Services</a>
    <?php endif;?>
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
  <div class="page-hero">
    <div style="position:relative;z-index:2;">
      <div id="location-chip" class="location-chip" style="margin-bottom:14px;" onclick="refreshLocation()">
        <span class="pin">📍</span><span id="location-text">Detecting location...</span>
      </div>
      <div class="hero-title">Hey, <span><?=htmlspecialchars(explode(' ',$userName)[0])?></span>! 👋<br>What are you looking for?</div>
      <div class="hero-subtitle">Discover shops &amp; services right around you</div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">
    <div class="search-section">
      <div class="search-bar" style="margin-bottom:14px;">
        <span class="search-icon">🔍</span>
        <input type="text" id="shop-search" placeholder="Search shops, products, services..." oninput="filterShops()"/>
        <button class="search-btn" onclick="fetchNearbyShops()">Search</button>
      </div>
      <div class="filter-pills" id="filter-pills">
        <div class="pill active" onclick="setFilter('all',this)">🌟 All</div>
        <div class="pill" onclick="setFilter('grocery',this)">🛒 Grocery</div>
        <div class="pill" onclick="setFilter('restaurant',this)">🍽 Restaurant</div>
        <div class="pill" onclick="setFilter('pharmacy',this)">💊 Pharmacy</div>
        <div class="pill" onclick="setFilter('plumbing',this)">🔧 Plumbing</div>
        <div class="pill" onclick="setFilter('electrical',this)">⚡ Electrical</div>
        <div class="pill" onclick="setFilter('cleaning',this)">🧹 Cleaning</div>
        <div class="pill" onclick="setFilter('electronics',this)">📱 Electronics</div>
      </div>
    </div>

    <div style="margin-bottom:24px;">
      <div class="section-title"><span class="section-title-dot">📍</span> Shops Near You</div>
      <div id="map"></div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
      <span style="font-weight:700;font-size:0.88rem;">Search Radius:</span>
      <div style="display:flex;gap:8px;">
        <button class="pill active" id="r1"  onclick="setRadius(1,this)">1 km</button>
        <button class="pill"        id="r5"  onclick="setRadius(5,this)">5 km</button>
        <button class="pill"        id="r10" onclick="setRadius(10,this)">10 km</button>
        <button class="pill"        id="r25" onclick="setRadius(25,this)">25 km</button>
      </div>
      <span style="font-size:0.82rem;color:var(--muted);" id="shops-count"></span>
    </div>

    <div class="section-title"><span class="section-title-dot">🏪</span> Available Shops</div>
    <div id="shops-container">
      <div style="text-align:center;padding:40px;"><div class="spinner-ll"></div><p style="color:var(--muted);margin-top:8px;">Fetching your location...</p></div>
    </div>
  </div>
</div>

<div class="cart-float" id="cart-float" style="display:none;" onclick="location.href='cart.php'">
  <div class="cart-float-info">
    <div class="cart-float-count" id="cart-count-label">0 items</div>
    <div class="cart-float-total" id="cart-total-label">₹0.00</div>
  </div>
  <div class="cart-float-action">View Cart 🛒</div>
</div>
<div id="toast-container"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let userLat=null,userLng=null,currentRadius=1,currentCategory='all',allShops=[],map,userMarker,shopMarkers=[];
let cart=JSON.parse(localStorage.getItem('ll_cart')||'{}');

document.addEventListener('DOMContentLoaded',()=>{initMap();getLocation();updateCartFloat();});

function initMap(){map=L.map('map',{zoomControl:true});L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);map.setView([12.9716,77.5946],13);}

function getLocation(){if(!navigator.geolocation){document.getElementById('location-text').textContent='Location not supported';return;}navigator.geolocation.getCurrentPosition(pos=>{userLat=pos.coords.latitude;userLng=pos.coords.longitude;map.setView([userLat,userLng],14);if(userMarker)map.removeLayer(userMarker);userMarker=L.circleMarker([userLat,userLng],{radius:10,fillColor:'#FF416C',color:'white',weight:3,fillOpacity:1}).addTo(map).bindPopup('<b>📍 You are here</b>');fetch(`https://nominatim.openstreetmap.org/reverse?lat=${userLat}&lon=${userLng}&format=json`).then(r=>r.json()).then(d=>{const a=d.address.suburb||d.address.neighbourhood||d.address.city||'Your Location';document.getElementById('location-text').textContent=a;}).catch(()=>document.getElementById('location-text').textContent='Location detected');fetchNearbyShops();},err=>{document.getElementById('location-text').textContent='Location denied';document.getElementById('shops-container').innerHTML='<div class="alert-ll info">⚠️ Please enable location to see nearby shops.</div>';},{enableHighAccuracy:true,timeout:10000});}

function refreshLocation(){document.getElementById('location-text').textContent='Refreshing...';getLocation();}

async function fetchNearbyShops(){if(!userLat)return;document.getElementById('shops-container').innerHTML='<div style="text-align:center;padding:40px;"><div class="spinner-ll"></div><p style="color:var(--muted)">Finding shops...</p></div>';try{const res=await fetch(`api/nearby_shops.php?lat=${userLat}&lng=${userLng}&radius=${currentRadius}&category=${currentCategory}`);const data=await res.json();allShops=data.shops||[];renderShops(allShops);renderMapMarkers(allShops);document.getElementById('shops-count').textContent=allShops.length>0?`${allShops.length} shop(s) found`:'';}catch(e){document.getElementById('shops-container').innerHTML='<div class="alert-ll danger">⚠️ Could not fetch shops.</div>';}}

const EMOJI={grocery:'🛒',restaurant:'🍽',pharmacy:'💊',electronics:'📱',clothing:'👗',plumbing:'🔧',electrical:'⚡',cleaning:'🧹',other:'🏪'};
const COLORS={grocery:'#11998e',restaurant:'#f7971e',pharmacy:'#4776E6',electronics:'#6C63FF',clothing:'#FF5F6D',plumbing:'#FF416C',electrical:'#FFC837',cleaning:'#38ef7d',other:'#999'};

function renderShops(shops){const q=document.getElementById('shop-search').value.toLowerCase();const f=q?shops.filter(s=>s.name.toLowerCase().includes(q)||(s.description||'').toLowerCase().includes(q)):shops;if(!f.length){document.getElementById('shops-container').innerHTML='<div class="empty-state"><div class="empty-icon">🏙</div><h3>No shops found nearby</h3><p>Try expanding the radius or a different category.</p></div>';return;}const html=f.map(s=>`<div class="card-ll shop-card" onclick="openShop(${s.shop_id})"><div class="shop-card-img" style="background:linear-gradient(135deg,${COLORS[s.category]||'#999'},#1a1a2e);"><span style="font-size:3.5rem;">${EMOJI[s.category]||'🏪'}</span><span class="category-badge">${s.category}</span></div><div class="shop-card-body"><div class="shop-name">${esc(s.name)}</div><div style="font-size:0.8rem;color:var(--muted);margin-top:3px;">${esc(s.address||'')}</div><div class="shop-meta"><span class="shop-dist">📍 ${parseFloat(s.distance_km).toFixed(1)} km</span><span class="shop-rating">⭐ ${parseFloat(s.rating||0).toFixed(1)}</span><span class="shop-open ${s.is_open?'open':'closed'}">${s.is_open?'Open':'Closed'}</span></div><div style="margin-top:12px;display:flex;gap:8px;"><button class="btn-ll btn-primary-ll btn-sm-ll" style="flex:1" onclick="event.stopPropagation();openShop(${s.shop_id})">🛒 Order Now</button><button class="btn-ll btn-outline-ll btn-sm-ll" onclick="event.stopPropagation();zoomToShop(${s.latitude},${s.longitude})">📍 Map</button></div></div></div>`).join('');document.getElementById('shops-container').innerHTML=`<div class="shops-grid">${html}</div>`;}

function renderMapMarkers(shops){shopMarkers.forEach(m=>map.removeLayer(m));shopMarkers=[];const bounds=userLat?[[userLat,userLng]]:[];shops.forEach(s=>{const icon=L.divIcon({html:`<div style="background:${COLORS[s.category]||'#FF416C'};color:white;padding:4px 8px;border-radius:20px;font-size:0.75rem;font-weight:800;white-space:nowrap;box-shadow:0 3px 10px rgba(0,0,0,0.3);">${EMOJI[s.category]||'🏪'} ${s.name}</div>`,className:'',iconAnchor:[0,20]});const m=L.marker([s.latitude,s.longitude],{icon}).addTo(map);m.bindPopup(`<div class="map-popup"><h4>${esc(s.name)}</h4><p>📍 ${esc(s.address||'')}</p><p>⭐ ${parseFloat(s.rating||0).toFixed(1)} | ${parseFloat(s.distance_km).toFixed(1)} km</p><button class="order-btn" onclick="openShop(${s.shop_id})">🛒 Order from this shop</button></div>`);shopMarkers.push(m);bounds.push([s.latitude,s.longitude]);});if(bounds.length>1){try{map.fitBounds(bounds,{padding:[40,40]});}catch(e){}}}

function zoomToShop(lat,lng){map.setView([lat,lng],16);window.scrollTo({top:document.getElementById('map').offsetTop-80,behavior:'smooth'});}
function openShop(id){window.location.href=`shop.php?id=${id}`;}
function setFilter(cat,el){currentCategory=cat;document.querySelectorAll('#filter-pills .pill').forEach(p=>p.classList.remove('active'));el.classList.add('active');fetchNearbyShops();}
function setRadius(r,btn){currentRadius=r;document.querySelectorAll('[id^="r"]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');fetchNearbyShops();}
function filterShops(){renderShops(allShops);}
function updateCartFloat(){const items=Object.values(cart);const total=items.reduce((s,i)=>s+i.price*i.qty,0);const count=items.reduce((s,i)=>s+i.qty,0);const f=document.getElementById('cart-float');if(count>0){f.style.display='flex';document.getElementById('cart-count-label').textContent=`${count} item${count>1?'s':''}`;document.getElementById('cart-total-label').textContent=`₹${total.toFixed(2)}`;}else f.style.display='none';}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
