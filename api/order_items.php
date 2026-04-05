<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$orderId = intval($_GET['order_id']??0);
if (!$orderId) jsonResponse(false,'Invalid order.');
$stmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.unit FROM order_items oi
     JOIN products p ON oi.product_id=p.product_id WHERE oi.order_id=?"
);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();
$total = array_sum(array_map(fn($i)=>$i['price']*$i['quantity'],$items));
echo json_encode(['success'=>true,'items'=>$items,'total'=>$total]);
