<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['role']!=='shop_owner') { header('Location: dashboard.php'); exit; }
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$shopId   = intval($_GET['shop_id']??0);
if ($shopId) {
    $sv = $pdo->prepare("SELECT * FROM shops WHERE shop_id=? AND owner_id=?");
    $sv->execute([$shopId,$userId]); $shop=$sv->fetch();
    if (!$shop) { header('Location: shop_dashboard.php'); exit; }
} else {
    $sv = $pdo->prepare("SELECT * FROM shops WHERE owner_id=? LIMIT 1");
    $sv->execute([$userId]); $shop=$sv->fetch();
    if (!$shop) { header('Location: add_shop.php'); exit; }
    $shopId = $shop['shop_id'];
}
$ms = $pdo->prepare("SELECT shop_id,name FROM shops WHERE owner_id=?");
$ms->execute([$userId]); $myShops=$ms->fetchAll();
$ps = $pdo->prepare("SELECT * FROM products WHERE shop_id=? ORDER BY name");
$ps->execute([$shopId]); $products=$ps->fetchAll();
$ss = $pdo->prepare("SELECT * FROM services WHERE shop_id=? ORDER BY name");
$ss->execute([$shopId]); $services=$ss->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Manage Products</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body{padding:20px;margin-top:-60px;position:relative;z-index:5;}
    .items-table{width:100%;border-collapse:collapse;}
    .items-table th,.items-table td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border);font-size:0.88rem;}
    .items-table th{font-weight:800;color:var(--muted);font-size:0.78rem;text-transform:uppercase;background:var(--light);}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:white;border-radius:var(--radius-xl);padding:28px;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;}
    .tab-btns{display:flex;gap:0;border-bottom:2px solid #f0f0f0;margin-bottom:20px;}
    .tab-btn{flex:1;padding:12px;text-align:center;cursor:pointer;font-weight:700;font-size:0.88rem;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-2px;transition:var(--transition);background:none;border-top:none;border-left:none;border-right:none;font-family:var(--font-body);}
    .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary);}
    .tab-pane{display:none;}.tab-pane.active{display:block;}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"><div class="sidebar-logo">Local<span>Link</span></div><div><div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div><div class="sidebar-user-info">Shop Owner</div></div></div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="shop_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="add_shop.php"><span class="nav-icon">➕</span> Add New Shop</a>
    <a href="manage_products.php" class="active"><span class="nav-icon">📦</span> Manage Products</a>
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
    <div style="position:relative;z-index:2;"><button class="back-btn" onclick="history.back()">← Back</button><div class="hero-title">Manage <span>Products</span> 📦</div><div class="hero-subtitle"><?=htmlspecialchars($shop['name'])?></div></div>
    <div class="hero-wave"></div>
  </div>
  <div class="content-body">
    <?php if(count($myShops)>1):?>
    <div class="card-ll" style="padding:14px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
      <label style="font-weight:700;font-size:0.88rem;flex-shrink:0;">Switch Shop:</label>
      <select class="form-control-ll" style="flex:1;" onchange="location.href='manage_products.php?shop_id='+this.value">
        <?php foreach($myShops as $s):?><option value="<?=$s['shop_id']?>" <?=$s['shop_id']==$shopId?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endforeach;?>
      </select>
    </div>
    <?php endif;?>
    <div class="card-ll" style="padding:0;overflow:hidden;">
      <div style="padding:16px 16px 0;">
        <div class="tab-btns">
          <button class="tab-btn active" onclick="switchTab('products',this)">📦 Products (<?=count($products)?>)</button>
          <button class="tab-btn" onclick="switchTab('services',this)">🔧 Services (<?=count($services)?>)</button>
        </div>
      </div>

      <div class="tab-pane active" id="tab-products">
        <div style="padding:0 16px 12px;display:flex;justify-content:flex-end;"><button class="btn-ll btn-primary-ll btn-sm-ll" onclick="openModal('product')">+ Add Product</button></div>
        <?php if(empty($products)):?>
          <div class="empty-state" style="padding:40px;"><div class="empty-icon">📦</div><h3>No products yet</h3></div>
        <?php else:?>
        <div style="overflow-x:auto;">
          <table class="items-table">
            <thead><tr><th>Product</th><th>Price</th><th>Unit</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($products as $p):?>
              <tr>
                <td><div style="font-weight:700;"><?=htmlspecialchars($p['name'])?></div></td>
                <td style="font-weight:800;color:var(--primary);">₹<?=number_format($p['price'],2)?></td>
                <td><?=htmlspecialchars($p['unit']??'piece')?></td>
                <td><?=$p['stock']?></td>
                <td><span class="status-badge <?=$p['is_available']?'delivered':'cancelled'?>"><?=$p['is_available']?'✅ Active':'❌ Hidden'?></span></td>
                <td style="display:flex;gap:6px;">
                  <button class="btn-ll btn-outline-ll btn-sm-ll" onclick="toggleProduct(<?=$p['product_id']?>,<?=$p['is_available']?>)"><?=$p['is_available']?'Hide':'Show'?></button>
                  <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="deleteProduct(<?=$p['product_id']?>)">🗑</button>
                </td>
              </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
        <?php endif;?>
      </div>

      <div class="tab-pane" id="tab-services">
        <div style="padding:0 16px 12px;display:flex;justify-content:flex-end;"><button class="btn-ll btn-primary-ll btn-sm-ll" onclick="openModal('service')">+ Add Service</button></div>
        <?php if(empty($services)):?>
          <div class="empty-state" style="padding:40px;"><div class="empty-icon">🔧</div><h3>No services yet</h3></div>
        <?php else:?>
        <div style="overflow-x:auto;">
          <table class="items-table">
            <thead><tr><th>Service</th><th>Price</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($services as $sv):?>
              <tr>
                <td><div style="font-weight:700;"><?=htmlspecialchars($sv['name'])?></div></td>
                <td style="font-weight:800;color:var(--primary);">₹<?=number_format($sv['price'],2)?></td>
                <td><?=$sv['duration_mins']?> mins</td>
                <td><span class="status-badge <?=$sv['is_available']?'delivered':'cancelled'?>"><?=$sv['is_available']?'✅ Active':'❌ Hidden'?></span></td>
                <td><button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);" onclick="deleteService(<?=$sv['service_id']?>)">🗑</button></td>
              </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
        <?php endif;?>
      </div>
    </div>
  </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal-overlay" id="product-modal">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;"><h3 style="font-family:var(--font-heading);font-weight:900;">Add Product</h3><button onclick="closeModal('product-modal')" style="background:none;border:none;font-size:1.4rem;cursor:pointer;">✕</button></div>
    <form onsubmit="addProduct(event)">
      <div class="form-group-ll"><label class="form-label-ll">Product Name *</label><input class="form-control-ll" type="text" id="p-name" placeholder="e.g. Fresh Tomatoes" required/></div>
      <div class="form-group-ll"><label class="form-label-ll">Description</label><textarea class="form-control-ll" id="p-desc" rows="2" placeholder="Short description"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group-ll"><label class="form-label-ll">Price (₹) *</label><input class="form-control-ll" type="number" id="p-price" step="0.01" min="0" placeholder="0.00" required/></div>
        <div class="form-group-ll"><label class="form-label-ll">Unit</label><select class="form-control-ll" id="p-unit"><option value="piece">piece</option><option value="kg">kg</option><option value="g">gram</option><option value="litre">litre</option><option value="pack">pack</option><option value="dozen">dozen</option><option value="box">box</option></select></div>
      </div>
      <div class="form-group-ll"><label class="form-label-ll">Stock</label><input class="form-control-ll" type="number" id="p-stock" value="100" min="0"/></div>
      <button type="submit" class="btn-ll btn-primary-ll btn-full">➕ Add Product</button>
    </form>
  </div>
</div>

<!-- ADD SERVICE MODAL -->
<div class="modal-overlay" id="service-modal">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;"><h3 style="font-family:var(--font-heading);font-weight:900;">Add Service</h3><button onclick="closeModal('service-modal')" style="background:none;border:none;font-size:1.4rem;cursor:pointer;">✕</button></div>
    <form onsubmit="addService(event)">
      <div class="form-group-ll"><label class="form-label-ll">Service Name *</label><input class="form-control-ll" type="text" id="s-name" placeholder="e.g. Pipe Repair" required/></div>
      <div class="form-group-ll"><label class="form-label-ll">Description</label><textarea class="form-control-ll" id="s-desc" rows="2"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group-ll"><label class="form-label-ll">Price (₹) *</label><input class="form-control-ll" type="number" id="s-price" step="0.01" min="0" required/></div>
        <div class="form-group-ll"><label class="form-label-ll">Duration (mins)</label><input class="form-control-ll" type="number" id="s-duration" value="60" min="15" step="15"/></div>
      </div>
      <button type="submit" class="btn-ll btn-primary-ll btn-full">➕ Add Service</button>
    </form>
  </div>
</div>

<div id="toast-container"></div>
<script>
const SHOP_ID=<?=$shopId?>;
function switchTab(name,btn){document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('tab-'+name).classList.add('active');btn.classList.add('active');}
function openModal(t){document.getElementById(t+'-modal').classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
async function addProduct(e){e.preventDefault();const res=await fetch('api/add_product.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({shop_id:SHOP_ID,name:document.getElementById('p-name').value,description:document.getElementById('p-desc').value,price:document.getElementById('p-price').value,unit:document.getElementById('p-unit').value,stock:document.getElementById('p-stock').value})});const data=await res.json();if(data.success){showToast('Product Added!','','success');setTimeout(()=>location.reload(),1200);}else showToast('Error',data.message||'Failed','error');}
async function addService(e){e.preventDefault();const res=await fetch('api/add_service.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({shop_id:SHOP_ID,name:document.getElementById('s-name').value,description:document.getElementById('s-desc').value,price:document.getElementById('s-price').value,duration_mins:document.getElementById('s-duration').value})});const data=await res.json();if(data.success){showToast('Service Added!','','success');setTimeout(()=>location.reload(),1200);}else showToast('Error',data.message||'Failed','error');}
async function toggleProduct(id,cur){const res=await fetch('api/toggle_product.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:id,is_available:cur?0:1})});const data=await res.json();if(data.success)location.reload();else showToast('Error',data.message,'error');}
async function deleteProduct(id){if(!confirm('Delete this product?'))return;const res=await fetch('api/delete_item.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'product',id})});const data=await res.json();if(data.success){showToast('Deleted!','','success');setTimeout(()=>location.reload(),900);}else showToast('Error',data.message,'error');}
async function deleteService(id){if(!confirm('Delete this service?'))return;const res=await fetch('api/delete_item.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'service',id})});const data=await res.json();if(data.success){showToast('Deleted!','','success');setTimeout(()=>location.reload(),900);}else showToast('Error',data.message,'error');}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
