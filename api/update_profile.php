<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$d    = json_decode(file_get_contents('php://input'),true);
$uid  = $_SESSION['user_id'];
$name = trim($d['name']??'');
$phone= trim($d['phone']??'');
$addr = trim($d['address']??'');
if (!$name) jsonResponse(false,'Name cannot be empty.');
$stmt = $pdo->prepare("UPDATE users SET name=?,phone=?,address=? WHERE user_id=?");
$stmt->execute([$name,$phone,$addr,$uid]);
$_SESSION['name'] = $name;
jsonResponse(true,'Profile updated successfully!');
