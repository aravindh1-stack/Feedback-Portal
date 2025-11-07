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
$username = trim($_POST['username'] ?? '');

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
  // Fetch the user record WITHOUT checking the password
  if ($role === 'student') {
    // Allow lookup by SIN number OR email for students
    $sql = "SELECT * FROM `students` WHERE `sin_number` = ? OR `email` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $username);
  } else {
    $sql = "SELECT * FROM `$table` WHERE `$userField` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $username);
  }
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

  // Prepare session for OTP
  $_SESSION['pending_user'] = $row; // Store user data to use after verification
  $_SESSION['pending_role'] = $role;
  $_SESSION['otp_email'] = $toEmail;

  $now = time();
  $hasUnexpiredOtp = isset($_SESSION['otp_code'], $_SESSION['otp_expires']) && ($now < (int)$_SESSION['otp_expires']);
  $lastSent = (int)($_SESSION['otp_last_sent'] ?? 0);

  if ($hasUnexpiredOtp) {
    // If more than 90s passed since last send, re-send a fresh OTP to help the user
    if ($lastSent && ($now - $lastSent) > 90) {
      $otp = generate_otp(6);
      $_SESSION['otp_code'] = $otp;
      $_SESSION['otp_expires'] = $now + 300;
      if (send_otp_email($toEmail, $otp)) {
        $_SESSION['otp_last_sent'] = $now;
        $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $toEmail);
        echo json_encode(['success' => true, 'message' => 'OTP re-sent.', 'email' => $masked]);
        exit;
      }
      echo json_encode(['success' => false, 'message' => 'Failed to resend OTP. Try again.']);
      exit;
    }
    // Otherwise, reuse same OTP without resending
    $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $toEmail);
    echo json_encode(['success' => true, 'message' => 'OTP already sent. Please check your email.', 'email' => $masked]);
    exit;
  }

  // No unexpired OTP: generate and send once
  $otp = generate_otp(6);
  $_SESSION['otp_code'] = $otp; // plain for simple compare
  $_SESSION['otp_expires'] = $now + 300; // 5 minutes

  $sent = send_otp_email($toEmail, $otp);
  if (!$sent) {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please check server logs.']);
    exit;
  }
  $_SESSION['otp_last_sent'] = $now;

  // Mask email for display on the front end
  $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $toEmail);
  echo json_encode(['success' => true, 'message' => 'OTP sent successfully', 'email' => $masked]);

} catch (Throwable $e) {
  error_log("OTP Request Error: " . $e->getMessage());
  echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
