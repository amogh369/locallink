<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])||$_SESSION['role']!=='shop_owner') jsonResponse(false,'Unauthorised.');
$d    = json_decode(file_get_contents('php://input'),true);
$type = $d['type']??'';
$id   = intval($d['id']??0);
$uid  = $_SESSION['user_id'];
if ($type==='product') {
    $stmt = $pdo->prepare(
        "DELETE FROM products WHERE product_id=? AND shop_id IN (SELECT shop_id FROM shops WHERE owner_id=?)"
    );
} elseif ($type==='service') {
    $stmt = $pdo->prepare(
        "DELETE FROM services WHERE service_id=? AND shop_id IN (SELECT shop_id FROM shops WHERE owner_id=?)"
    );
} else {
    jsonResponse(false,'Invalid type.');
}
$stmt->execute([$id,$uid]);
$stmt->rowCount()>0 ? jsonResponse(true,'Deleted.') : jsonResponse(false,'Item not found.');
