    <?php
// AJAX: resend OTP to the email stored in session
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/otp_helpers.php';

if (!isset($_SESSION['otp_email'])) {
  echo json_encode(['success' => false, 'message' => 'OTP session expired. Please login again.']);
  exit;
}

try {
  $now = time();
  $to = $_SESSION['otp_email'];
  $lastSent = (int)($_SESSION['otp_last_sent'] ?? 0);
  $sendingLock = (int)($_SESSION['otp_sending'] ?? 0);

  // Prevent double-click or concurrent sends within 5 seconds
  if ($sendingLock && ($now - $sendingLock) < 5) {
    $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $to);
    echo json_encode(['success' => true, 'message' => 'OTP already being sent. Please check your email.', 'email' => $masked]);
    exit;
  }
  $_SESSION['otp_sending'] = $now;

  // Throttle resend to once every 30 seconds
  if ($lastSent && ($now - $lastSent) < 30) {
    $wait = 30 - ($now - $lastSent);
    echo json_encode(['success' => false, 'message' => 'Please wait '.$wait.'s before resending OTP.']);
    unset($_SESSION['otp_sending']);
    exit;
  }

  // Generate a fresh OTP and extend expiry
  $otp = generate_otp(6);
  $_SESSION['otp_code'] = $otp;
  $_SESSION['otp_expires'] = $now + 300; // extend 5 minutes

  if (send_otp_email($to, $otp)) {
    $_SESSION['otp_last_sent'] = $now;
    $masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $to);
    echo json_encode(['success' => true, 'message' => 'A new OTP has been sent.', 'email' => $masked]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to resend OTP. Try again later.']);
  }
  unset($_SESSION['otp_sending']);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
