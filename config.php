<?php
// LocalLink - config.php
// Reads DATABASE_URL from _env.php which is written at container startup

$envFile = __DIR__ . '/_env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

$dbUrl = '';
if (isset($_ENV['DATABASE_URL']) && $_ENV['DATABASE_URL'] !== '') {
    $dbUrl = $_ENV['DATABASE_URL'];
}

if ($dbUrl === '') {
    die(json_encode(array(
        'success' => false,
        'message' => 'DATABASE_URL is not set. Check Render environment variables.'
    )));
}

$p    = parse_url($dbUrl);
$host = isset($p['host']) ? $p['host'] : '';
$port = isset($p['port']) ? (int)$p['port'] : 6543;
$db   = isset($p['path']) ? ltrim($p['path'], '/') : 'postgres';
$user = isset($p['user']) ? $p['user'] : '';
$pass = isset($p['pass']) ? urldecode($p['pass']) : '';
$dsn  = "pgsql:host=" . $host . ";port=" . $port . ";dbname=" . $db . ";sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ));
} catch (PDOException $e) {
    die(json_encode(array(
        'success' => false,
        'message' => 'DB connection failed: ' . $e->getMessage()
    )));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function dbq($sql) { global $pdo; return $pdo->query($sql); }
function dbRow($sql) { global $pdo; $r = $pdo->query($sql)->fetch(); return $r ? $r : null; }
function dbAll($sql) { global $pdo; return $pdo->query($sql)->fetchAll(); }
function dbCount($sql) { global $pdo; $r = $pdo->query($sql)->fetch(); return isset($r['c']) ? (int)$r['c'] : 0; }

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function jsonResponse($success, $message, $data = array()) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(array('success' => $success, 'message' => $message), $data));
    exit;
}
?>
