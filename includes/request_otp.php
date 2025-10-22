<?php
// /includes/request_otp.php
// AJAX: validate USERNAME/ROLE and send OTP without redirecting

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/otp_helpers.php';

$role = $_POST['role'] ?? '';
$username = $_POST['username'] ?? '';

// FIX: Check only for role and username, not password
if (!$role || !$username) {
  echo json_encode(['success' => false, 'message' => 'Missing username or role.']);
  exit;
}

if ($role === 'student') {
  $table = 'students';
  $userField = 'sin_number';
} elseif ($role === 'faculty') {
  $table = 'faculty';
  $userField = 'email';
} else {
  $table = 'admin';
  $userField = 'username';
}

try {
  // FIX: Fetch the user record WITHOUT checking the password
  $sql = "SELECT * FROM `$table` WHERE `$userField` = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (!($row = $result->fetch_assoc())) {
    echo json_encode(['success' => false, 'message' => 'No account found with this username/role.']);
    exit;
  }
  
  // FIX: The entire password validation block is removed.

  // Resolve email to send OTP to
  $toEmail = $row['email'] ?? null;
  if (!$toEmail && $role === 'admin') {
    $mailCfg = include __DIR__ . '/../config/mail.php';
    $toEmail = $mailCfg['admin_otp_email'] ?? null;
  }
  
  if (!$toEmail) {
    echo json_encode(['success' => false, 'message' => 'No email is configured for this account. Cannot send OTP.']);
    exit;
  }

  // Generate OTP and store in session
  $otp = generate_otp(6);
  $_SESSION['pending_user'] = $row; // Store user data to use after verification
  $_SESSION['pending_role'] = $role;
  $_SESSION['otp_code'] = $otp; // Store the plain OTP for simple comparison
  $_SESSION['otp_expires'] = time() + 300; // 5 minutes expiry
  $_SESSION['otp_email'] = $toEmail;

  // Send the OTP email
  $sent = send_otp_email($toEmail, $otp);
  if (!$sent) {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please check server logs.']);
    exit;
  }

  // Mask email for display on the front end
  $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $toEmail);
  echo json_encode(['success' => true, 'message' => 'OTP sent successfully', 'email' => $masked]);

} catch (Throwable $e) {
  error_log("OTP Request Error: " . $e->getMessage());
  echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
