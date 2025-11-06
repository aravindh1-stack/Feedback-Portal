 <?php
// Database connection settings
$host = 'localhost';
$db   = 'college_feedback';
$user = 'root';
$pass = '';

// Enable MySQLi exceptions to avoid silent failures
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    throw new Exception('Database connection failed: ' . $conn->connect_error);
}
?>