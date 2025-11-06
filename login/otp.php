<?php
session_start();
// If no pending OTP, redirect back to login
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email'])) {
  header('Location: index.php');
  exit;
}
$email = $_SESSION['otp_email'];
$masked = preg_replace('/(^.).*(.@.*$)/', '$1***$2', $email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP - College Feedback Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:#ffffff; color:#1f2937; min-height:100vh; display:flex; justify-content:center; align-items:center; padding:2rem; }
    .otp-card { width:100%; max-width:460px; border:1px solid #e5e7eb; border-radius:16px; padding:2rem; box-shadow:0 10px 40px rgba(0,0,0,0.08); }
    h1 { font-size:1.5rem; font-weight:800; margin-bottom:.25rem; }
    p { color:#6b7280; margin-bottom:1.25rem; }
    .otp-inputs { display:flex; gap:.5rem; justify-content:space-between; }
    .otp-inputs input { width:3rem; height:3.25rem; text-align:center; font-size:1.25rem; border:1px solid #d1d5db; border-radius:8px; }
    .otp-inputs input:focus { outline:none; border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
    .btn { margin-top:1.25rem; width:100%; padding:.875rem 1rem; border:none; border-radius:10px; background:#4f46e5; color:#fff; font-weight:700; cursor:pointer; }
    .btn:disabled { background:#9ca3af; }
    .actions { display:flex; justify-content:space-between; margin-top:.75rem; }
    .link { color:#4f46e5; text-decoration:none; font-weight:600; }
    .message { margin-top:1rem; display:none; font-weight:600; }
    .error { color:#dc2626; }
    .success { color:#15803d; }
  </style>
</head>
<body>
  <div class="otp-card">
    <h1>Verify OTP</h1>
    <p>We've sent a 6-digit code to <strong><?php echo htmlspecialchars($masked); ?></strong>. Enter it below to continue.</p>
    <form method="post" action="../includes/verify_otp.php" id="otpForm">
      <div class="otp-inputs">
        <input type="text" inputmode="numeric" maxlength="1" name="d1" required>
        <input type="text" inputmode="numeric" maxlength="1" name="d2" required>
        <input type="text" inputmode="numeric" maxlength="1" name="d3" required>
        <input type="text" inputmode="numeric" maxlength="1" name="d4" required>
        <input type="text" inputmode="numeric" maxlength="1" name="d5" required>
        <input type="text" inputmode="numeric" maxlength="1" name="d6" required>
      </div>
      <button type="submit" class="btn" id="verifyBtn">Verify</button>
    </form>
    <div class="actions">
      <a class="link" href="index.php">Back to Login</a>
      <a class="link" href="../includes/send_otp.php">Resend OTP</a>
    </div>
    <div class="message error" id="errorMsg"></div>
    <div class="message success" id="successMsg"></div>
  </div>
  <script>
    // Auto focus between inputs
    const inputs = document.querySelectorAll('.otp-inputs input');
    inputs.forEach((input, idx) => {
      input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0,1);
        if (input.value && idx < inputs.length - 1) inputs[idx+1].focus();
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && idx > 0) inputs[idx-1].focus();
      });
    });
  </script>
</body>
</html>
