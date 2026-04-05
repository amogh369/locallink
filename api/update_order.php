<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');

$d       = json_decode(file_get_contents('php://input'), true);
$orderId = intval($d['order_id'] ?? 0);
$status  = $d['status'] ?? '';
$allowed = ['pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];

if (!$orderId || !in_array($status,$allowed)) jsonResponse(false,'Invalid data.');

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

// Update order
if ($role === 'customer') {
    $stmt = $pdo->prepare(
        "UPDATE orders SET status=? WHERE order_id=? AND user_id=? AND status='pending'"
    );
    $stmt->execute([$status,$orderId,$userId]);
} else {
    $stmt = $pdo->prepare(
        "UPDATE orders o SET status=?
         FROM shops s WHERE o.shop_id=s.shop_id AND o.order_id=? AND s.owner_id=?"
    );
    $stmt->execute([$status,$orderId,$userId]);
}

if ($stmt->rowCount() === 0) jsonResponse(false,'Could not update order.');

// Fetch order details for notifications
$info = $pdo->prepare(
    "SELECT o.user_id AS customer_id, o.total_amount, s.name AS shop_name,
            s.owner_id, u.name AS customer_name
     FROM orders o
     JOIN shops s ON o.shop_id=s.shop_id
     JOIN users u ON o.user_id=u.user_id
     WHERE o.order_id=?"
);
$info->execute([$orderId]);
$row = $info->fetch();

if (!$row) jsonResponse(true,'Order updated.');

$cid      = $row['customer_id'];
$oid      = $row['owner_id'];
$shop     = $row['shop_name'];
$cname    = $row['customer_name'];
$total    = '₹'.number_format($row['total_amount'],2);

// Customer notifications per status
$msgs = [
    'confirmed'        => ["Order #$orderId Confirmed! ✅",           "$shop confirmed your order ($total). Getting it ready for you."],
    'preparing'        => ["Order #$orderId Being Prepared 👨‍🍳",     "$shop is preparing your order ($total). On its way soon!"],
    'out_for_delivery' => ["Order #$orderId Out for Delivery 🚴",     "Your order from $shop is on the way ($total)! Please be available."],
    'delivered'        => ["Order #$orderId Delivered! 🎉",           "Your order from $shop ($total) has been delivered. Enjoy!"],
    'cancelled'        => ["Order #$orderId Cancelled ❌",            "Your order from $shop ($total) has been cancelled."],
];

$n = $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'order')");

// Notify customer
if (isset($msgs[$status])) {
    $n->execute([$cid, $msgs[$status][0], $msgs[$status][1]]);
}

// Notify owner when customer cancels
if ($role === 'customer' && $status === 'cancelled') {
    $n->execute([$oid,"Order #$orderId Cancelled by Customer ❌","$cname cancelled Order #$orderId ($total)."]);
}

jsonResponse(true,'Order updated to '.$status);
