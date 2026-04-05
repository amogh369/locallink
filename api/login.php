<?php
require_once '../config.php';
$d     = json_decode(file_get_contents('php://input'), true);
$email = trim($d['email']    ?? '');
$pass  = $d['password'] ?? '';
if (!$email || !$pass) jsonResponse(false,'Email and password required.');

$stmt = $pdo->prepare("SELECT user_id,name,email,password_hash,role FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass,$user['password_hash'])) {
    jsonResponse(false,'Invalid email or password.');
}
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$redirect = $user['role'] === 'shop_owner' ? 'shop_dashboard.php' : 'dashboard.php';
jsonResponse(true,'Login successful!',['redirect'=>$redirect,'role'=>$user['role']]);
