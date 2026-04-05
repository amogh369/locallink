<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');

$d         = json_decode(file_get_contents('php://input'), true);
$userId    = $_SESSION['user_id'];
$shopId    = intval($d['shop_id'] ?? 0);
$cartItems = $d['cart'] ?? [];
$address   = $d['delivery_address'] ?? '';
$payment   = in_array($d['payment'] ?? 'cash',['cash','online']) ? ($d['payment'] ?? 'cash') : 'cash';

if (!$shopId || empty($cartItems)) jsonResponse(false,'Invalid order data.');

try {
    $pdo->beginTransaction();

    $total = 0;
    foreach ($cartItems as $item) {
        $total += floatval($item['price']) * intval($item['qty']);
    }

    // Insert order
    $stmt = $pdo->prepare(
        "INSERT INTO orders (user_id,shop_id,total_amount,delivery_address,payment_method)
         VALUES (?,?,?,?,?) RETURNING order_id"
    );
    $stmt->execute([$userId,$shopId,$total,$address,$payment]);
    $orderId = $stmt->fetchColumn();

    // Insert order items
    $ins = $pdo->prepare(
        "INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)"
    );
    foreach ($cartItems as $item) {
        $ins->execute([$orderId, intval($item['id']), intval($item['qty']), floatval($item['price'])]);
    }

    $pdo->commit();

    // Fetch shop + owner info
    $info = $pdo->prepare(
        "SELECT s.name AS shop_name, s.owner_id, u.name AS customer_name
         FROM shops s JOIN users u ON u.user_id=? WHERE s.shop_id=?"
    );
    $info->execute([$userId,$shopId]);
    $row = $info->fetch();

    $shopName     = $row['shop_name']     ?? 'the shop';
    $ownerId      = $row['owner_id']      ?? null;
    $customerName = $row['customer_name'] ?? 'A customer';
    $itemCount    = count($cartItems);
    $totalFmt     = number_format($total,2);

    // Notify customer
    $n = $pdo->prepare(
        "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'order')"
    );
    $n->execute([
        $userId,
        "Order Placed! 🎉",
        "Your order #$orderId from $shopName has been placed. Total: ₹$totalFmt. We'll notify you once confirmed."
    ]);

    // Notify owner
    if ($ownerId) {
        $n->execute([
            $ownerId,
            "New Order Received! 🛒",
            "$customerName placed Order #$orderId ($itemCount item" . ($itemCount>1?'s':'') . ") — Total: ₹$totalFmt. Please confirm."
        ]);
    }

    jsonResponse(true,'Order placed!',['order_id'=>$orderId,'total'=>$total]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false,'Order failed: '.$e->getMessage());
}
