<?php // api/cancel_booking.php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$d = json_decode(file_get_contents('php://input'),true);
$bid = intval($d['booking_id']??0); $uid = $_SESSION['user_id'];
if (!$bid) jsonResponse(false,'Invalid booking.');
$stmt = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE booking_id=? AND user_id=? AND status='pending'");
$stmt->execute([$bid,$uid]);
$stmt->rowCount()>0 ? jsonResponse(true,'Booking cancelled.') : jsonResponse(false,'Cannot cancel.');
