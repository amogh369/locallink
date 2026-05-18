<?php
require_once 'config.php';
requireLogin();
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];

$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nc->execute([$userId]); $notifCount = (int)$nc->fetchColumn();

// Fetch orders
$stmt = $pdo->prepare("
    SELECT o.*,
           s.name     AS shop_name,
           s.category AS shop_category,
           COUNT(oi.item_id) AS item_count
    FROM orders o
    JOIN shops s ON o.shop_id = s.shop_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id, o.user_id, o.shop_id, o.total_amount, o.status,
             o.delivery_address, o.delivery_lat, o.delivery_lng, o.notes,
             o.payment_method, o.payment_status, o.created_at, o.updated_at,
             s.name, s.category
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Fetch already-reviewed order IDs for this user (so we don't show rating again)
$rStmt = $pdo->prepare("SELECT order_id FROM reviews WHERE user_id = ?");
$rStmt->execute([$userId]);
$reviewedOrderIds = array_column($rStmt->fetchAll(), 'order_id');
$reviewedOrderIds = array_flip($reviewedOrderIds); // for quick O(1) lookup

$STEPS  = ['pending'=>0,'confirmed'=>1,'preparing'=>2,'out_for_delivery'=>3,'delivered'=>4];
$LABELS = ['Placed','Confirmed','Preparing','On the way','Delivered'];
$ICONS  = ['📝','✅','👨‍🍳','🚴','🎉'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – My Orders</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .content-body { padding:20px; margin-top:-60px; position:relative; z-index:5; }

    /* ── Items panel ── */
    .items-panel { display:none; margin-top:16px;
                   border-top:2px dashed var(--border); padding-top:16px; }
    .items-panel.open { display:block; }
    .item-row { display:flex; align-items:center; gap:12px; padding:10px 14px;
                border-radius:var(--radius-md); background:var(--light); margin-bottom:8px; }
    .item-icon { width:42px; height:42px; border-radius:var(--radius-sm); background:white;
                 display:flex; align-items:center; justify-content:center;
                 font-size:1.4rem; box-shadow:var(--shadow-sm); flex-shrink:0; }
    .item-name { flex:1; font-weight:700; font-size:0.9rem; color:var(--dark); }
    .item-meta { font-size:0.76rem; color:var(--muted); margin-top:2px; }
    .item-qty  { background:var(--gradient-main); color:white; border-radius:20px;
                 padding:3px 12px; font-size:0.82rem; font-weight:800; flex-shrink:0; }
    .item-price{ font-weight:900; color:var(--primary); font-size:1rem;
                 flex-shrink:0; min-width:72px; text-align:right; }
    .items-total { display:flex; justify-content:space-between; align-items:center;
                   padding:12px 14px; border-radius:var(--radius-md);
                   background:var(--dark); margin-top:10px; }

    .view-btn { cursor:pointer; background:white; border:2px solid var(--primary);
                color:var(--primary); border-radius:var(--radius-lg);
                padding:7px 18px; font-size:0.82rem; font-weight:700;
                font-family:var(--font-body); transition:var(--transition); }
    .view-btn:hover, .view-btn.open { background:var(--primary); color:white; }

    /* ── Star Rating ── */
    .rating-zone {
      margin-top:16px; border-top:2px dashed #f0c060; padding-top:16px;
    }
    .rating-box {
      background:linear-gradient(135deg,#fff9ee,#fff3cd);
      border:1.5px solid #ffc837; border-radius:var(--radius-lg);
      padding:18px 20px;
    }
    .rating-title {
      font-family:var(--font-heading); font-size:1rem; font-weight:900;
      color:var(--dark); margin-bottom:4px;
    }
    .rating-sub { font-size:0.82rem; color:var(--muted); margin-bottom:14px; }

    .stars-row { display:flex; gap:6px; margin-bottom:14px; align-items:center; }
    .star-btn {
      font-size:2rem; cursor:pointer; color:#ddd;
      transition:transform 0.15s, color 0.15s;
      background:none; border:none; padding:0; line-height:1;
      user-select:none;
    }
    .star-btn:hover { transform:scale(1.2); }
    .star-btn.active { color:#FFC837; }
    .star-label {
      font-size:0.85rem; font-weight:700; color:#e6a800;
      margin-left:8px; min-width:80px;
    }

    .review-textarea {
      width:100%; padding:10px 14px; border:1.5px solid #e0c060;
      border-radius:var(--radius-md); font-family:var(--font-body);
      font-size:0.9rem; resize:vertical; min-height:70px;
      outline:none; background:white; margin-bottom:12px;
      transition:border-color 0.2s;
    }
    .review-textarea:focus { border-color:#FFC837; box-shadow:0 0 0 3px rgba(255,200,55,0.18); }

    .submit-review-btn {
      background:linear-gradient(135deg,#FFC837,#e6a800);
      color:#1a1a2e; border:none; border-radius:var(--radius-lg);
      padding:10px 24px; font-weight:900; font-size:0.92rem;
      cursor:pointer; font-family:var(--font-body);
      transition:transform 0.2s, box-shadow 0.2s;
      box-shadow:0 4px 14px rgba(255,200,55,0.35);
    }
    .submit-review-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(255,200,55,0.45); }
    .submit-review-btn:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

    .already-reviewed {
      display:flex; align-items:center; gap:8px;
      background:rgba(17,153,142,0.08); border:1.5px solid rgba(17,153,142,0.25);
      border-radius:var(--radius-md); padding:10px 14px;
      font-size:0.88rem; font-weight:700; color:var(--success);
      margin-top:14px;
    }

    .spinner-sm { width:24px; height:24px; border-radius:50%;
                  border:3px solid rgba(255,65,108,0.2);
                  border-top-color:var(--primary);
                  animation:spin 0.7s linear infinite; margin:14px auto; }
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Local<span>Link</span></div>
    <div>
      <div class="sidebar-user-name"><?=htmlspecialchars($userName)?></div>
      <div class="sidebar-user-info">Customer</div>
    </div>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">🏠</span> Home</a>
    <a href="orders.php" class="active"><span class="nav-icon">📦</span> My Orders</a>
    <a href="bookings.php"><span class="nav-icon">📅</span> My Bookings</a>
    <a href="profile.php"><span class="nav-icon">👤</span> My Profile</a>
    <a href="notifications.php"><span class="nav-icon">🔔</span> Notifications
      <?php if($notifCount>0):?>
        <span style="margin-left:auto;background:var(--primary);color:white;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:20px;"><?=$notifCount?></span>
      <?php endif;?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <button class="sidebar-logout" onclick="logout()">🚪 Sign Out</button>
  </div>
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
      <div class="hero-title">My <span>Orders</span> 📦</div>
      <div class="hero-subtitle"><?=count($orders)?> order<?=count($orders)!=1?'s':''?> placed</div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <div class="content-body">

    <?php if(empty($orders)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>No orders yet!</h3>
        <p>Explore nearby shops and place your first order.</p>
        <a href="dashboard.php" class="btn-ll btn-primary-ll" style="margin-top:16px;display:inline-flex;">🛒 Browse Shops</a>
      </div>

    <?php else: foreach($orders as $o):
      $stepIdx     = $STEPS[$o['status']] ?? 0;
      $isCancelled = $o['status'] === 'cancelled';
      $isDelivered = $o['status'] === 'delivered';
      $alreadyReviewed = isset($reviewedOrderIds[$o['order_id']]);
    ?>

    <div class="card-ll" style="margin-bottom:16px;padding:18px;">

      <!-- ── ORDER HEADER ── -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
          <div style="font-family:var(--font-heading);font-weight:800;font-size:1.05rem;">
            🏪 <?=htmlspecialchars($o['shop_name'])?>
          </div>
          <div style="font-size:0.8rem;color:var(--muted);margin-top:4px;">
            Order #<?=$o['order_id']?> &nbsp;|&nbsp; 🕐 <?=date('d M Y, h:i A',strtotime($o['created_at']))?>
          </div>
          <div style="font-size:0.82rem;margin-top:4px;">
            💳 <?=ucfirst($o['payment_method']??'cash')?> &nbsp;|&nbsp;
            📦 <?=$o['item_count']?> item<?=$o['item_count']!=1?'s':''?>
          </div>
          <?php if($o['delivery_address']): ?>
          <div style="font-size:0.8rem;color:var(--muted);margin-top:4px;">
            📍 <?=htmlspecialchars($o['delivery_address'])?>
          </div>
          <?php endif; ?>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div style="font-family:var(--font-heading);font-size:1.4rem;font-weight:900;color:var(--primary);">
            ₹<?=number_format($o['total_amount'],2)?>
          </div>
          <span class="status-badge <?=$o['status']?>" style="margin-top:6px;display:inline-flex;">
            <?=$isCancelled?'❌':($isDelivered?'🎉':'🔄')?>
            <?=ucwords(str_replace('_',' ',$o['status']))?>
          </span>
        </div>
      </div>

      <!-- ── TRACKING STEPPER ── -->
      <?php if(!$isCancelled): ?>
      <div style="margin-top:18px;">
        <div class="order-stepper">
          <?php for($i=0;$i<count($LABELS);$i++):
            $cls = $i<$stepIdx?'done':($i===$stepIdx?'active':'');?>
          <div class="step-item <?=$cls?>">
            <div class="step-dot"><?=$i<$stepIdx?'✓':$ICONS[$i]?></div>
            <div class="step-label"><?=$LABELS[$i]?></div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── BUTTONS ROW ── -->
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button class="view-btn" id="vbtn-<?=$o['order_id']?>" onclick="toggleItems(<?=$o['order_id']?>,this)">
          📋 View Items ▾
        </button>
        <?php if($o['status']==='pending'): ?>
        <button class="btn-ll btn-sm-ll" style="background:rgba(235,51,73,0.1);color:var(--danger);"
          onclick="cancelOrder(<?=$o['order_id']?>)">❌ Cancel Order</button>
        <?php endif; ?>
      </div>

      <!-- ── ITEMS PANEL ── -->
      <div class="items-panel" id="items-<?=$o['order_id']?>">
        <div id="items-content-<?=$o['order_id']?>">
          <div class="spinner-sm"></div>
        </div>
      </div>

      <!-- ── STAR RATING (only for delivered + not yet reviewed) ── -->
      <?php if($isDelivered): ?>
      <div class="rating-zone">
        <?php if($alreadyReviewed): ?>
          <div class="already-reviewed">
            ✅ You have already rated this order. Thank you for your feedback!
          </div>
        <?php else: ?>
          <div class="rating-box" id="rating-box-<?=$o['order_id']?>">
            <div class="rating-title">🌟 Rate Your Experience</div>
            <div class="rating-sub">How was your order from <?=htmlspecialchars($o['shop_name'])?>? Tap the stars to rate.</div>

            <!-- Stars -->
            <div class="stars-row" id="stars-<?=$o['order_id']?>">
              <?php for($star=1;$star<=5;$star++): ?>
              <button class="star-btn"
                data-star="<?=$star?>"
                data-order="<?=$o['order_id']?>"
                onmouseover="hoverStars(<?=$o['order_id']?>,<?=$star?>)"
                onmouseout="resetStars(<?=$o['order_id']?>)"
                onclick="selectStar(<?=$o['order_id']?>,<?=$star?>)">★</button>
              <?php endfor; ?>
              <span class="star-label" id="star-label-<?=$o['order_id']?>">Tap to rate</span>
            </div>

            <!-- Comment box -->
            <textarea class="review-textarea"
              id="review-text-<?=$o['order_id']?>"
              placeholder="Write a comment (optional)..."></textarea>

            <!-- Submit -->
            <button class="submit-review-btn"
              id="review-btn-<?=$o['order_id']?>"
              onclick="submitReview(<?=$o['order_id']?>,<?=$o['shop_id']?>)">
              ⭐ Submit Rating
            </button>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
    <?php endforeach; endif; ?>

  </div>
</div>

<div id="toast-container"></div>

<script>
// ── Selected stars state ──────────────────────────────────
const selectedStars = {};

const STAR_LABELS = { 1:'😞 Poor', 2:'😐 Fair', 3:'🙂 Good', 4:'😊 Very Good', 5:'🤩 Excellent!' };

function hoverStars(orderId, upTo) {
  if (selectedStars[orderId]) return; // already selected, don't change on hover
  fillStars(orderId, upTo, false);
}
function resetStars(orderId) {
  if (selectedStars[orderId]) {
    fillStars(orderId, selectedStars[orderId], true);
  } else {
    fillStars(orderId, 0, false);
    document.getElementById('star-label-' + orderId).textContent = 'Tap to rate';
  }
}
function selectStar(orderId, star) {
  selectedStars[orderId] = star;
  fillStars(orderId, star, true);
}
function fillStars(orderId, upTo, isSelected) {
  const stars = document.querySelectorAll('#stars-' + orderId + ' .star-btn');
  stars.forEach(btn => {
    const s = parseInt(btn.dataset.star);
    btn.classList.toggle('active', s <= upTo);
  });
  const label = document.getElementById('star-label-' + orderId);
  if (upTo > 0) {
    label.textContent = STAR_LABELS[upTo] || '';
  }
}

// ── Submit review ─────────────────────────────────────────
async function submitReview(orderId, shopId) {
  const rating = selectedStars[orderId];
  if (!rating) {
    showToast('', 'Please select a star rating first.', 'warning');
    return;
  }
  const comment = document.getElementById('review-text-' + orderId).value.trim();
  const btn     = document.getElementById('review-btn-' + orderId);
  btn.innerHTML = '⏳ Submitting...'; btn.disabled = true;

  try {
    const res  = await fetch('api/submit_review.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, shop_id: shopId, rating, comment })
    });
    const data = await res.json();
    if (data.success) {
      // Replace rating box with thank-you message
      document.getElementById('rating-box-' + orderId).outerHTML = `
        <div class="already-reviewed">
          ✅ Thank you! You gave ${rating} star${rating>1?'s':''} to ${esc(data.shop_name || 'the shop')}. Your feedback helps the community!
        </div>`;
      showToast('Rating Submitted! ⭐', 'Thank you for your feedback.', 'success');
    } else {
      showToast('Error', data.message || 'Could not submit rating.', 'error');
      btn.innerHTML = '⭐ Submit Rating'; btn.disabled = false;
    }
  } catch(e) {
    showToast('Error', 'Network error. Please try again.', 'error');
    btn.innerHTML = '⭐ Submit Rating'; btn.disabled = false;
  }
}

// ── Toggle items panel ────────────────────────────────────
async function toggleItems(orderId, btn) {
  const panel   = document.getElementById('items-' + orderId);
  const content = document.getElementById('items-content-' + orderId);
  const isOpen  = panel.classList.contains('open');
  if (isOpen) {
    panel.classList.remove('open'); btn.classList.remove('open');
    btn.innerHTML = '📋 View Items ▾'; return;
  }
  panel.classList.add('open'); btn.classList.add('open');
  btn.innerHTML = '📋 Hide Items ▴';
  if (content.dataset.loaded === '1') return;
  content.innerHTML = '<div class="spinner-sm"></div>';
  try {
    const res  = await fetch('api/order_items.php?order_id=' + orderId);
    const data = await res.json();
    if (!data.success || !data.items || data.items.length === 0) {
      content.innerHTML = '<div style="text-align:center;padding:16px;color:var(--muted);">📭 No items found.</div>';
      content.dataset.loaded = '1'; return;
    }
    let html = data.items.map(item => `
      <div class="item-row">
        <div class="item-icon">${getEmoji(item.product_name)}</div>
        <div style="flex:1;">
          <div class="item-name">${esc(item.product_name)}</div>
          <div class="item-meta">₹${parseFloat(item.price).toFixed(2)} per ${esc(item.unit||'piece')}</div>
        </div>
        <div class="item-qty">× ${item.quantity}</div>
        <div class="item-price">₹${(item.price*item.quantity).toFixed(2)}</div>
      </div>`).join('');
    html += `<div class="items-total">
      <span style="font-size:0.88rem;font-weight:700;color:rgba(255,255,255,0.7);">${data.count} item${data.count!==1?'s':''} &nbsp;|&nbsp; Total</span>
      <span style="font-family:var(--font-heading);font-size:1.25rem;font-weight:900;color:#FFC837;">₹${parseFloat(data.total).toFixed(2)}</span>
    </div>`;
    content.innerHTML = html; content.dataset.loaded = '1';
  } catch(e) {
    content.innerHTML = '<div class="alert-ll danger">⚠️ Could not load items.</div>';
  }
}

// ── Cancel order ──────────────────────────────────────────
async function cancelOrder(orderId) {
  if (!confirm('Cancel this order?')) return;
  const res  = await fetch('api/update_order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ order_id: orderId, status: 'cancelled' })
  });
  const data = await res.json();
  if (data.success) { showToast('Order Cancelled','','warning'); setTimeout(()=>location.reload(),1400); }
  else showToast('Error', data.message||'Failed.', 'error');
}

// ── Utilities ─────────────────────────────────────────────
const EMOJIS = {tomato:'🍅',onion:'🧅',potato:'🥔',milk:'🥛',bread:'🍞',egg:'🥚',rice:'🍚',sugar:'🍬',tea:'🍵',coffee:'☕',banana:'🍌',apple:'🍎',mango:'🥭',chicken:'🍗',fish:'🐟',soap:'🧴',oil:'🫙',biscuit:'🍪',water:'💧',juice:'🧃'};
function getEmoji(name) { const n=(name||'').toLowerCase(); for(const[k,v]of Object.entries(EMOJIS))if(n.includes(k))return v; return '📦'; }
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function showToast(title,msg,type='info'){const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};const t=document.createElement('div');t.className=`toast-ll ${type}`;t.innerHTML=`<div class="toast-icon">${icons[type]}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),4200);}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');}
async function logout(){await fetch('api/logout.php');window.location.href='index.php';}
</script>
</body>
</html>
