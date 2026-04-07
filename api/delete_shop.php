<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false, 'Not logged in.');

$d      = json_decode(file_get_contents('php://input'), true);
$uid    = $_SESSION['user_id'];
$shopId = intval($d['shop_id'] ?? 0);

if (!$shopId) jsonResponse(false, 'Invalid shop.');

// Verify ownership before deleting
$check = $pdo->prepare("SELECT shop_id, name FROM shops WHERE shop_id=? AND owner_id=?");
$check->execute([$shopId, $uid]);
$shop = $check->fetch();

if (!$shop) jsonResponse(false, 'Shop not found or access denied.');

$shopName = $shop['name'];

try {
    $pdo->beginTransaction();

    // Delete in order to respect foreign keys:
    // cart → order_items → orders → bookings → services → products → reviews → shop

    // Remove cart items for this shop
    $pdo->prepare("DELETE FROM cart WHERE shop_id=?")->execute([$shopId]);

    // Remove order items for orders from this shop
    $pdo->prepare(
        "DELETE FROM order_items WHERE order_id IN (SELECT order_id FROM orders WHERE shop_id=?)"
    )->execute([$shopId]);

    // Remove orders
    $pdo->prepare("DELETE FROM orders WHERE shop_id=?")->execute([$shopId]);

    // Remove bookings
    $pdo->prepare("DELETE FROM bookings WHERE shop_id=?")->execute([$shopId]);

    // Remove services
    $pdo->prepare("DELETE FROM services WHERE shop_id=?")->execute([$shopId]);

    // Remove products
    $pdo->prepare("DELETE FROM products WHERE shop_id=?")->execute([$shopId]);

    // Remove reviews
    $pdo->prepare("DELETE FROM reviews WHERE shop_id=?")->execute([$shopId]);

    // Finally delete the shop itself
    $pdo->prepare("DELETE FROM shops WHERE shop_id=? AND owner_id=?")->execute([$shopId, $uid]);

    // Notify the owner
    $notif = $pdo->prepare(
        "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'system')"
    );
    $notif->execute([
        $uid,
        'Shop Deleted',
        'Your shop "' . $shopName . '" has been permanently deleted.'
    ]);

    $pdo->commit();
    jsonResponse(true, 'Shop deleted successfully.');

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Delete failed: ' . $e->getMessage());
}
?>
