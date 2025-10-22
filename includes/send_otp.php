<?php
session_start();
require_once __DIR__ . '/otp_helpers.php';

if (!isset($_SESSION['otp_email'])) {
  $_SESSION['error'] = 'Session expired. Please login again.';
  header('Location: ../login/index.php');
  exit;
}

// Generate a new OTP and extend expiry
$otp = generate_otp(6);
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expires'] = time() + 300; // 5 minutes from now

$to = $_SESSION['otp_email'];
if (send_otp_email($to, $otp)) {
  $_SESSION['success'] = 'A new OTP has been sent to your email.';
} else {
  $_SESSION['error'] = 'Failed to resend OTP. Try again later.';
}

header('Location: ../login/otp.php');
exit;
