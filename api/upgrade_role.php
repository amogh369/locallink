<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$d      = json_decode(file_get_contents('php://input'),true);
$action = $d['action']??'';
$uid    = $_SESSION['user_id'];
if ($action==='become_owner') {
    if ($_SESSION['role']==='shop_owner') jsonResponse(false,'You are already a shop owner.');
    $pdo->prepare("UPDATE users SET role='shop_owner' WHERE user_id=?")->execute([$uid]);
    $_SESSION['role'] = 'shop_owner';
    jsonResponse(true,'You are now a Shop Owner!',['redirect'=>'shop_dashboard.php']);
}
jsonResponse(false,'Invalid action.');
