<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) jsonResponse(false, 'Not logged in.');

$d      = json_decode(file_get_contents('php://input'), true);
$uid    = $_SESSION['user_id'];
$shopId = intval($d['shop_id'] ?? 0);

if (!$shopId) jsonResponse(false, 'Invalid shop.');

$name   = trim($d['name'] ?? '');
$cat    = $d['category'] ?? 'other';
$desc   = trim($d['description'] ?? '');
$phone  = trim($d['phone'] ?? '');
$lat    = floatval($d['latitude'] ?? 0);
$lng    = floatval($d['longitude'] ?? 0);
$addr   = trim($d['address'] ?? '');
$open   = $d['opening_time'] ?? '08:00';
$close  = $d['closing_time'] ?? '22:00';
$isOpen = isset($d['is_open']) ? intval($d['is_open']) : 1;

if (!$name)         jsonResponse(false, 'Shop name is required.');
if (!$lat || !$lng) jsonResponse(false, 'Please select shop location on the map.');

$cats = array('grocery','restaurant','pharmacy','electronics','clothing','plumbing','electrical','cleaning','other');
if (!in_array($cat, $cats)) $cat = 'other';

// Verify this shop belongs to the logged-in user
$check = $pdo->prepare("SELECT shop_id FROM shops WHERE shop_id=? AND owner_id=?");
$check->execute([$shopId, $uid]);
if (!$check->fetch()) jsonResponse(false, 'Shop not found or access denied.');

$stmt = $pdo->prepare(
    "UPDATE shops SET name=?,category=?,description=?,phone=?,latitude=?,longitude=?,
     address=?,opening_time=?,closing_time=?,is_open=?
     WHERE shop_id=? AND owner_id=?"
);
$stmt->execute([
    $name, $cat, $desc, $phone, $lat, $lng,
    $addr, $open, $close, $isOpen,
    $shopId, $uid
]);

jsonResponse(true, 'Shop updated successfully!', array('shop_id' => $shopId));
?>
