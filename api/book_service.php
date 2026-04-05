<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false,'Not logged in.');
$d         = json_decode(file_get_contents('php://input'),true);
$userId    = $_SESSION['user_id'];
$serviceId = intval($d['service_id']??0);
$shopId    = intval($d['shop_id']??0);
$date      = $d['booking_date']??'';
$slot      = $d['time_slot']??'';
$notes     = trim($d['notes']??'');
if (!$serviceId||!$shopId||!$date||!$slot) jsonResponse(false,'All booking fields required.');

$svc = $pdo->prepare("SELECT price,name FROM services WHERE service_id=?");
$svc->execute([$serviceId]);
$row = $svc->fetch();
if (!$row) jsonResponse(false,'Service not found.');

// Check slot conflict
$conflict = $pdo->prepare(
    "SELECT booking_id FROM bookings WHERE shop_id=? AND booking_date=? AND time_slot=? AND status NOT IN ('cancelled')"
);
$conflict->execute([$shopId,$date,$slot]);
if ($conflict->fetch()) jsonResponse(false,'This slot is already booked. Choose another.');

$stmt = $pdo->prepare(
    "INSERT INTO bookings (user_id,service_id,shop_id,booking_date,time_slot,notes,total_price)
     VALUES (?,?,?,?,?,?,?) RETURNING booking_id"
);
$stmt->execute([$userId,$serviceId,$shopId,$date,$slot,$notes,$row['price']]);
$bookingId = $stmt->fetchColumn();

// Notify customer
$n = $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'booking')");
$n->execute([$userId,"Booking Confirmed! 📅","Your booking for {$row['name']} on $date at $slot is confirmed."]);

// Notify owner
$owner = $pdo->prepare("SELECT owner_id FROM shops WHERE shop_id=?");
$owner->execute([$shopId]);
$o = $owner->fetch();
if ($o) $n->execute([$o['owner_id'],"New Booking! 📅","Service: {$row['name']} on $date at $slot."]);

jsonResponse(true,'Booking confirmed!',['booking_id'=>$bookingId]);
