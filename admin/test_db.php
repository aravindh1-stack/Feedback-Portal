<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

// Try multiple possible config paths to avoid fatal errors
$base = __DIR__;
$candidates = [
    $base . '/../config/db.php',
    $base . '/config/db.php',
    $base . '/db.php',
    dirname($base, 2) . '/config/db.php',
    dirname($base, 2) . '/includes/db.php',
];

$usedPath = null;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        include_once $p;
        $usedPath = $p;
        break;
    }
}

if ($usedPath === null) {
    echo "DB config not found. Tried paths:\n";
    foreach ($candidates as $p) { echo " - $p\n"; }
    exit(1);
}

echo "Using config: $usedPath\n";

if (!isset($conn)) {
    echo "DB variable \$conn is not set after including config.\n";
    exit(2);
}

if ($conn instanceof mysqli) {
    if ($conn->connect_error) {
        echo "DB FAIL\n";
        echo "connect_error: " . $conn->connect_error . "\n";
        exit(3);
    }
    echo "DB OK\n";
    echo "host_info: " . $conn->host_info . "\n";
    echo "server_info: " . $conn->server_info . "\n";
} else {
    echo "\$conn is not a mysqli instance. Type: " . gettype($conn) . "\n";
}
