<?php
require_once '../config.php';
$d = json_decode(file_get_contents('php://input'), true);
$name  = trim($d['name']  ?? '');
$email = trim($d['email'] ?? '');
$phone = trim($d['phone'] ?? '');
$pass  = $d['password'] ?? '';
$role  = in_array($d['role'] ?? '', ['customer','shop_owner']) ? $d['role'] : 'customer';

if (!$name || !$email || !$pass)       jsonResponse(false,'Name, email and password are required.');
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonResponse(false,'Invalid email address.');
if (strlen($pass) < 6)                 jsonResponse(false,'Password must be at least 6 characters.');

$chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$chk->execute([$email]);
if ($chk->fetch()) jsonResponse(false,'Email already registered. Please login.');

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    "INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?) RETURNING user_id"
);
$stmt->execute([$name,$email,$phone,$hash,$role]);
$row = $stmt->fetch();

if ($row) {
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['name']    = $name;
    $_SESSION['email']   = $email;
    $_SESSION['role']    = $role;
    $redirect = $role === 'shop_owner' ? 'shop_dashboard.php' : 'dashboard.php';
    jsonResponse(true,'Account created!',['redirect'=>$redirect,'role'=>$role]);
}
jsonResponse(false,'Registration failed. Try again.');
