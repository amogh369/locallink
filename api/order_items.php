<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false, 'Not logged in.');

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) jsonResponse(false, 'Invalid order.');

// LEFT JOIN so items still show even if product was later deleted
// COALESCE gives a fallback name if product no longer exists
$stmt = $pdo->prepare("
    SELECT
        oi.item_id,
        oi.order_id,
        oi.quantity,
        oi.price,
        COALESCE(p.name, '[Product no longer available]') AS product_name,
        COALESCE(p.unit, 'piece') AS unit,
        oi.product_id
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.item_id ASC
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

echo json_encode([
    'success' => true,
    'items'   => $items,
    'total'   => round($total, 2),
    'count'   => count($items)
]);
?>
