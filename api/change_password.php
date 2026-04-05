<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$d      = json_decode(file_get_contents('php://input'),true);
$uid    = $_SESSION['user_id'];
$cur    = $d['current']??'';
$newpwd = $d['newpwd']??'';
if (!$cur||!$newpwd)    jsonResponse(false,'Both fields are required.');
if (strlen($newpwd)<6)  jsonResponse(false,'New password must be at least 6 characters.');
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?");
$stmt->execute([$uid]);
$row = $stmt->fetch();
if (!password_verify($cur,$row['password_hash'])) jsonResponse(false,'Current password is incorrect.');
$hash = password_hash($newpwd,PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([$hash,$uid]);
jsonResponse(true,'Password changed successfully!');
