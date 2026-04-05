<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])||$_SESSION['role']!=='shop_owner') jsonResponse(false,'Unauthorised.');
$d = json_decode(file_get_contents('php://input'),true);
$pid = intval($d['product_id']??0); $val = intval($d['is_available']??0); $uid = $_SESSION['user_id'];
$stmt = $pdo->prepare(
    "UPDATE products p SET is_available=? FROM shops s WHERE p.shop_id=s.shop_id AND p.product_id=? AND s.owner_id=?"
);
$stmt->execute([$val,$pid,$uid]);
$stmt->rowCount()>0 ? jsonResponse(true,'Updated') : jsonResponse(false,'Not found');
