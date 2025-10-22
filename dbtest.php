<?php
// Centralized DB connection test using config/db.php
// Respects environment variables if provided
require_once __DIR__ . '/config/db.php';

if (isset($conn) && $conn instanceof mysqli) {
    echo 'DB Connected!';
} else {
    echo 'DB Connection failed.';
}
?>