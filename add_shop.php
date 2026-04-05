<?php // api/add_shop.php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='shop_owner') jsonResponse(false,'Unauthorised.');
$d = json_decode(file_get_contents('php://input'),true);
$ownerId = $_SESSION['user_id'];
$name    = trim($d['name']??''); $cat = $d['category']??'other';
$desc    = trim($d['description']??''); $phone = trim($d['phone']??'');
$lat     = floatval($d['latitude']??0); $lng = floatval($d['longitude']??0);
$addr    = trim($d['address']??''); $open = $d['opening_time']??'08:00'; $close = $d['closing_time']??'22:00';
$cats    = ['grocery','restaurant','pharmacy','electronics','clothing','plumbing','electrical','cleaning','other'];
if (!$name)              jsonResponse(false,'Shop name required.');
if (!$lat||!$lng)        jsonResponse(false,'Select location on map.');
if (!in_array($cat,$cats)) $cat='other';
$stmt = $pdo->prepare(
    "INSERT INTO shops (owner_id,name,description,category,latitude,longitude,address,phone,opening_time,closing_time)
     VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING shop_id"
);
$stmt->execute([$ownerId,$name,$desc,$cat,$lat,$lng,$addr,$phone,$open,$close]);
$id = $stmt->fetchColumn();
jsonResponse(true,'Shop registered!',['shop_id'=>$id]);
