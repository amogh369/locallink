<?php
require_once '../config.php';

// Any logged in user can register a shop - auto upgrade to shop_owner
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Not logged in.');
}

$d       = json_decode(file_get_contents('php://input'), true);
$ownerId = $_SESSION['user_id'];
$name    = trim($d['name'] ?? '');
$cat     = $d['category'] ?? 'other';
$desc    = trim($d['description'] ?? '');
$phone   = trim($d['phone'] ?? '');
$lat     = floatval($d['latitude'] ?? 0);
$lng     = floatval($d['longitude'] ?? 0);
$addr    = trim($d['address'] ?? '');
$open    = $d['opening_time'] ?? '08:00';
$close   = $d['closing_time'] ?? '22:00';

if (!$name)        jsonResponse(false, 'Shop name required.');
if (!$lat || !$lng) jsonResponse(false, 'Please select shop location on the map.');

$cats = array('grocery','restaurant','pharmacy','electronics','clothing','plumbing','electrical','cleaning','other');
if (!in_array($cat, $cats)) $cat = 'other';

// Auto upgrade user to shop_owner if they are a customer
if ($_SESSION['role'] !== 'shop_owner') {
    $pdo->prepare("UPDATE users SET role='shop_owner' WHERE user_id=?")->execute([$ownerId]);
    $_SESSION['role'] = 'shop_owner';
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO shops (owner_id,name,description,category,latitude,longitude,address,phone,opening_time,closing_time)
         VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING shop_id"
    );
    $stmt->execute([$ownerId, $name, $desc, $cat, $lat, $lng, $addr, $phone, $open, $close]);
    $shopId = $stmt->fetchColumn();

    // Notify the owner
    $notif = $pdo->prepare(
        "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'system')"
    );
    $notif->execute([
        $ownerId,
        'Shop Registered! 🏪',
        'Your shop "' . $name . '" has been registered successfully. Add products to start receiving orders!'
    ]);

    jsonResponse(true, 'Shop registered!', array('shop_id' => $shopId));

} catch (Exception $e) {
    jsonResponse(false, 'Failed to register shop: ' . $e->getMessage());
}
?>
