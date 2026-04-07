<?php
require_once 'config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$shopId = intval($_GET['id'] ?? 0);

if (!$shopId) { header('Location: shop_dashboard.php'); exit; }

// Fetch shop — must belong to this user
$stmt = $pdo->prepare("SELECT * FROM shops WHERE shop_id=? AND owner_id=?");
$stmt->execute([$shopId, $userId]);
$shop = $stmt->fetch();
if (!$shop) { header('Location: shop_dashboard.php'); exit; }

$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount = (int)$nc->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Edit Shop</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    #pick-map{height:260px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);border:3px solid white;cursor:crosshair;}
    .delete-zone{max-width:600px;margin:0 auto 40px;padding:20px;border-radius:var(--radius-lg);background:rgba(235,51,73,0.06);border:1.5px solid rgba(235,51,73,0.2);}
    .delete-zone h4{font-family:var(--font-heading);font-weight:900;color:var(--danger);margin-bottom:6px;}
    .delete-zone p{font-size:0.85rem;color:var(--muted);margin-bottom:14px;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Local<span>Link</span></div>
    <div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div><div class="sidebar-user-info">Shop Owner</div></div>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Browse Shops</a>
    <a href="shop_dashboard.php" class="active"><span class="nav-icon">📊</span> Shop Dashboard</a>
    <a href="add_shop.php"><span class="nav-icon">➕</span> Add New Shop</a>
    <a href="manage_products.php"><span class="nav-icon">📦</span> Manage Products</a>
    <a href="shop_orders.php"><span class="nav-icon">🛒</span> All Orders</a>
    <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications
      <?php if($notifCount>0):?><span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span><?php endif;?>
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
      <div class="hero-title">Edit <span>Shop</span> ✏️</div>
      <div class="hero-subtitle"><?=htmlspecialchars($shop['name'])?></div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">

    <!-- EDIT FORM -->
    <div class="card-ll" style="padding:24px;max-width:600px;margin:0 auto 24px;">
      <div id="edit-alert" style="display:none;"></div>
      <form onsubmit="saveShop(event)">

        <div class="form-group-ll">
          <label class="form-label-ll">Shop Name *</label>
          <div class="input-group-ll"><span class="input-icon">🏪</span>
          <input class="form-control-ll" type="text" id="shop-name"
            value="<?=htmlspecialchars($shop['name'])?>" required/></div>
        </div>

        <div class="form-group-ll">
          <label class="form-label-ll">Category *</label>
          <select class="form-control-ll" id="shop-cat" required>
            <?php
            $cats = array('grocery'=>'🛒 Grocery','restaurant'=>'🍽 Restaurant','pharmacy'=>'💊 Pharmacy',
                          'electronics'=>'📱 Electronics','clothing'=>'👗 Clothing','plumbing'=>'🔧 Plumbing',
                          'electrical'=>'⚡ Electrical','cleaning'=>'🧹 Cleaning','other'=>'🏪 Other');
            foreach ($cats as $val => $label):
            ?>
            <option value="<?=$val?>" <?=$shop['category']===$val?'selected':''?>><?=$label?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group-ll">
          <label class="form-label-ll">Description</label>
          <textarea class="form-control-ll" id="shop-desc" rows="3"><?=htmlspecialchars($shop['description']??'')?></textarea>
        </div>

        <div class="form-group-ll">
          <label class="form-label-ll">Phone Number</label>
          <div class="input-group-ll"><span class="input-icon">📞</span>
          <input class="form-control-ll" type="tel" id="shop-phone"
            value="<?=htmlspecialchars($shop['phone']??'')?>" maxlength="10"/></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="form-group-ll">
            <label class="form-label-ll">Opening Time</label>
            <input class="form-control-ll" type="time" id="shop-open"
              value="<?=date('H:i',strtotime($shop['opening_time']))?>"/>
          </div>
          <div class="form-group-ll">
            <label class="form-label-ll">Closing Time</label>
            <input class="form-control-ll" type="time" id="shop-close"
              value="<?=date('H:i',strtotime($shop['closing_time']))?>"/>
          </div>
        </div>

        <!-- Shop open/closed toggle -->
        <div class="form-group-ll">
          <label class="form-label-ll">Shop Status</label>
          <div style="display:flex;gap:12px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:0.9rem;">
              <input type="radio" name="is_open" value="1" <?=$shop['is_open']?'checked':''?>/> 🟢 Open
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:0.9rem;">
              <input type="radio" name="is_open" value="0" <?=!$shop['is_open']?'checked':''?>/> 🔴 Closed
            </label>
          </div>
        </div>

        <!-- Map -->
        <div class="form-group-ll">
          <label class="form-label-ll">📍 Shop Location — click map to update</label>
          <div id="pick-map"></div>
          <div style="font-size:0.82rem;color:var(--muted);margin-top:6px;">Current pin shows your saved location. Click to move it.</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
            <div><label class="form-label-ll">Latitude</label>
              <input class="form-control-ll" type="text" id="shop-lat"
                value="<?=$shop['latitude']?>" readonly/></div>
            <div><label class="form-label-ll">Longitude</label>
              <input class="form-control-ll" type="text" id="shop-lng"
                value="<?=$shop['longitude']?>" readonly/></div>
          </div>
        </div>

        <div class="form-group-ll">
          <label class="form-label-ll">Address</label>
          <div class="input-group-ll"><span class="input-icon">📍</span>
          <input class="form-control-ll" type="text" id="shop-address"
            value="<?=htmlspecialchars($shop['address']??'')?>"/></div>
        </div>

        <button type="submit" class="btn-ll btn-primary-ll btn-full" id="save-btn">
          💾 Save Changes
        </button>
      </form>
    </div>

    <!-- DELETE ZONE -->
    <div class="delete-zone">
      <h4>⚠️ Delete This Shop</h4>
      <p>This will permanently delete the shop, all its products, services, and orders. This cannot be undone.</p>
      <div id="confirm-delete" style="display:none;margin-bottom:12px;">
        <div class="alert-ll danger" style="margin-bottom:12px;">
          ⚠️ Are you sure? Type <strong>DELETE</strong> below to confirm.
        </div>
        <input class="form-control-ll" type="text" id="delete-confirm-input"
          placeholder='Type DELETE here' style="margin-bottom:12px;"/>
        <div style="display:flex;gap:10px;">
          <button class="btn-ll btn-full"
            style="background:var(--danger);color:white;"
            onclick="confirmDelete()">🗑 Yes, Delete Shop</button>
          <button class="btn-ll btn-outline-ll btn-full"
            onclick="document.getElementById('confirm-delete').style.display='none'">Cancel</button>
        </div>
      </div>
      <button class="btn-ll btn-full"
        id="delete-btn"
        style="background:rgba(235,51,73,0.1);color:var(--danger);border:1.5px solid rgba(235,51,73,0.3);"
        onclick="showDeleteConfirm()">
        🗑 Delete This Shop
      </button>
    </div>

  </div>
</div>

<div id="toast-container"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const SHOP_ID = <?=$shopId?>;
let pickMap, pickMarker;
let pickedLat = <?=$shop['latitude']?>;
let pickedLng = <?=$shop['longitude']?>;

document.addEventListener('DOMContentLoaded', function() {
  pickMap = L.map('pick-map');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(pickMap);

  // Show current pin
  pickMap.setView([pickedLat, pickedLng], 15);
  pickMarker = L.marker([pickedLat, pickedLng]).addTo(pickMap)
    .bindPopup('<b>Current shop location</b>').openPopup();

  pickMap.on('click', function(e) { setPin(e.latlng.lat, e.latlng.lng); });
});

function setPin(lat, lng) {
  pickedLat = parseFloat(lat).toFixed(7);
  pickedLng = parseFloat(lng).toFixed(7);
  document.getElementById('shop-lat').value = pickedLat;
  document.getElementById('shop-lng').value = pickedLng;
  if (pickMarker) pickMap.removeLayer(pickMarker);
  pickMarker = L.marker([lat, lng]).addTo(pickMap)
    .bindPopup('<b>New location</b>').openPopup();
}

async function saveShop(e) {
  e.preventDefault();
  var btn = document.getElementById('save-btn');
  btn.innerHTML = '⏳ Saving...'; btn.disabled = true;

  var isOpen = document.querySelector('input[name="is_open"]:checked').value;

  var res = await fetch('api/edit_shop.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      shop_id:      SHOP_ID,
      name:         document.getElementById('shop-name').value,
      category:     document.getElementById('shop-cat').value,
      description:  document.getElementById('shop-desc').value,
      phone:        document.getElementById('shop-phone').value,
      opening_time: document.getElementById('shop-open').value,
      closing_time: document.getElementById('shop-close').value,
      is_open:      parseInt(isOpen),
      latitude:     pickedLat,
      longitude:    pickedLng,
      address:      document.getElementById('shop-address').value
    })
  });
  var data = await res.json();
  if (data.success) {
    showAlert('Changes saved! ✅', 'success');
    setTimeout(function(){ location.href = 'shop_dashboard.php'; }, 1500);
  } else {
    showAlert(data.message || 'Failed to save.', 'danger');
    btn.innerHTML = '💾 Save Changes'; btn.disabled = false;
  }
}

function showDeleteConfirm() {
  document.getElementById('confirm-delete').style.display = 'block';
  document.getElementById('delete-btn').style.display = 'none';
}

async function confirmDelete() {
  var input = document.getElementById('delete-confirm-input').value.trim();
  if (input !== 'DELETE') {
    showToast('', 'Type DELETE exactly to confirm.', 'warning');
    return;
  }
  var res = await fetch('api/delete_shop.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({shop_id: SHOP_ID})
  });
  var data = await res.json();
  if (data.success) {
    showToast('Shop Deleted', 'Redirecting...', 'success');
    setTimeout(function(){ location.href = 'shop_dashboard.php'; }, 1500);
  } else {
    showToast('Error', data.message || 'Delete failed.', 'error');
  }
}

function showAlert(msg, type) {
  var el = document.getElementById('edit-alert');
  el.className = 'alert-ll ' + (type === 'success' ? 'success' : 'danger');
  el.innerHTML = (type === 'success' ? '✅ ' : '⚠️ ') + msg;
  el.style.display = 'flex';
}
function showToast(title,msg,type){var icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};var t=document.createElement('div');t.className='toast-ll '+type;t.innerHTML='<div class="toast-icon">'+icons[type]+'</div><div class="toast-body"><div class="toast-title">'+title+'</div><div class="toast-msg">'+msg+'</div></div>';document.getElementById('toast-container').appendChild(t);setTimeout(function(){t.remove();},4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
