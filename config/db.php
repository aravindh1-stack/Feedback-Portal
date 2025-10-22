 <?php
// Database connection settings
$host = 'sql200.infinityfree.com';
$db   = 'if0_39483711_feedback_system';
$user = 'if0_39483711';
$pass = 'Sample001122';

// Enable MySQLi exceptions to avoid silent failures
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    throw new Exception('Database connection failed: ' . $conn->connect_error);
}
?>