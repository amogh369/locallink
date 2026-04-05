<?php
require_once '../config.php';
$lat      = floatval($_GET['lat']      ?? 12.9716);
$lng      = floatval($_GET['lng']      ?? 77.5946);
$radius   = floatval($_GET['radius']   ?? 5);
$category = $_GET['category'] ?? 'all';

$catClause = ($category !== 'all') ? "AND s.category = :cat" : "";

$sql = "
    SELECT s.*,
           u.name  AS owner_name,
           u.phone AS owner_phone,
           (6371 * ACOS(
               COS(RADIANS(:lat)) * COS(RADIANS(s.latitude::float)) *
               COS(RADIANS(s.longitude::float) - RADIANS(:lng)) +
               SIN(RADIANS(:lat)) * SIN(RADIANS(s.latitude::float))
           )) AS distance_km
    FROM shops s
    JOIN users u ON s.owner_id = u.user_id
    WHERE s.is_open = 1 $catClause
    HAVING (6371 * ACOS(
               COS(RADIANS(:lat2)) * COS(RADIANS(s.latitude::float)) *
               COS(RADIANS(s.longitude::float) - RADIANS(:lng2)) +
               SIN(RADIANS(:lat2)) * SIN(RADIANS(s.latitude::float))
           )) <= :radius
    ORDER BY distance_km ASC
    LIMIT 50
";

// PostgreSQL doesn't allow alias in HAVING — use subquery
$sql = "
    SELECT * FROM (
        SELECT s.*,
               u.name  AS owner_name,
               u.phone AS owner_phone,
               (6371 * ACOS(GREATEST(-1, LEAST(1,
                   COS(RADIANS(:lat)) * COS(RADIANS(s.latitude::float)) *
                   COS(RADIANS(s.longitude::float) - RADIANS(:lng)) +
                   SIN(RADIANS(:lat)) * SIN(RADIANS(s.latitude::float))
               )))) AS distance_km
        FROM shops s
        JOIN users u ON s.owner_id = u.user_id
        WHERE s.is_open = 1 $catClause
    ) sub
    WHERE distance_km <= :radius
    ORDER BY distance_km ASC
    LIMIT 50
";

$params = [':lat' => $lat, ':lng' => $lng, ':radius' => $radius];
if ($category !== 'all') $params[':cat'] = $category;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();

foreach ($shops as &$s) {
    $s['distance_km'] = round(floatval($s['distance_km']), 2);
}
echo json_encode(['success' => true, 'shops' => $shops, 'count' => count($shops)]);
