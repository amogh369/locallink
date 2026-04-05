<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])||$_SESSION['role']!=='shop_owner') jsonResponse(false,'Unauthorised.');
$d = json_decode(file_get_contents('php://input'),true);
$userId = $_SESSION['user_id'];
$shopId = intval($d['shop_id']??0);
$name   = trim($d['name']??'');
$desc   = trim($d['description']??'');
$price  = floatval($d['price']??0);
$dur    = intval($d['duration_mins']??60);
if (!$shopId||!$name||$price<=0) jsonResponse(false,'Name and price required.');
$own = $pdo->prepare("SELECT shop_id FROM shops WHERE shop_id=? AND owner_id=?");
$own->execute([$shopId,$userId]);
if (!$own->fetch()) jsonResponse(false,'Shop not found.');
$stmt = $pdo->prepare(
    "INSERT INTO services (shop_id,name,description,price,duration_mins) VALUES (?,?,?,?,?) RETURNING service_id"
);
$stmt->execute([$shopId,$name,$desc,$price,$dur]);
jsonResponse(true,'Service added!',['service_id'=>$stmt->fetchColumn()]);
