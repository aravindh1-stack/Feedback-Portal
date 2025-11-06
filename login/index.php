<?php
// Prevent caching so Back navigation doesn't show stale disabled button
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - College Feedback Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Your existing CSS styles... */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background-color: #ffffff; color: #1f2937; line-height: 1.6; min-height: 100vh; display: flex; flex-direction: column; }
    .header { background: #ffffff; border-bottom: 1px solid #e5e7eb; padding: 1.5rem 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .header-content { max-width: 1200px; margin: 0 auto; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; }
    .logo-section { display: flex; align-items: center; gap: 12px; }
    .logo { width: 40px; height: 40px; background: #4f46e5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem; }
    .brand-text { color: #1f2937; font-size: 1.5rem; font-weight: 700; }
    .header-info { background: #f9fafb; color: #6b7280; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 8px; border: 1px solid #e5e7eb; }
    .main-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 2rem; }
    .login-container { max-width: 900px; width: 100%; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 24px; box-shadow: 0 20px 60px rgba(79,70,229,0.15); overflow: hidden; display: grid; grid-template-columns: 1fr 1fr; min-height: 600px; }
    .left-panel { background: #ffffff; padding: 3.25rem; display: flex; flex-direction: column; justify-content: center; }
    .form-header { margin-bottom: 2.5rem; text-align: center; }
    .form-title { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; }
    .form-subtitle { color: #6b7280; font-size: 1rem; }
    .form-group { margin-bottom: 1.5rem; }
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; font-size: 0.875rem; }
    .form-control { width: 100%; padding: 0.875rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
    .input-group { position: relative; }
    .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; }
    .input-group .form-control { padding-left: 2.75rem; }
    .login-btn { background: #4f46e5; color: white; width: 100%; padding: 0.875rem 1.5rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 1rem; }
    .error-message { background: #fef2f2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-top: 1rem; text-align: center; font-weight: 500; display: none; }
    .success-message { background: #f0fdf4; color: #15803d; padding: 1rem; border-radius: 8px; margin-top: 1rem; text-align: center; font-weight: 500; display: none; }
    .visual-panel { background: linear-gradient(135deg, #6d28d9 0%, #4f46e5 100%); padding: 3rem; display: flex; align-items: center; justify-content: center; color: #fff; }
    .visual-content { text-align: center; }
    .footer { background: #fff; border-top: 1px solid #e5e7eb; color: #6b7280; text-align: center; padding: 2rem 0; width: 100%; }
    @media (max-width: 768px) { .login-container { grid-template-columns: 1fr; } .visual-panel { display: none; } }
    .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s ease-in-out infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="main-container">
    <div class="login-container">
      <?php include __DIR__ . '/welcome.php'; ?>
      <?php include __DIR__ . '/login_form.php'; ?>
    </div>
  </main>
  <?php include __DIR__ . '/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('loginForm');
      const loginBtn = document.getElementById('loginBtn');
      const errorMessage = document.getElementById('errorMessage');
      const errorText = document.getElementById('errorText');
      const successMessage = document.getElementById('successMessage');
      const successText = document.getElementById('successText');
      const otpSection = document.getElementById('otpSection');
      const otpHint = document.getElementById('otpHint');
      const otpInputs = ['d1','d2','d3','d4','d5','d6'].map(id => document.getElementById(id));
      
      otpInputs.forEach((input, idx) => {
        if (!input) return;
        input.addEventListener('input', () => {
          input.value = input.value.replace(/\D/g, '').slice(0, 1);
          if (input.value && idx < otpInputs.length - 1) {
            otpInputs[idx + 1].focus();
          }
        });
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Backspace' && !input.value && idx > 0) {
            otpInputs[idx - 1].focus();
          }
        });
      });

      let awaitingOtp = false;

      function resetLoginButton() {
        if (!loginBtn) return;
        loginBtn.disabled = false;
        loginBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
      }
      resetLoginButton();

      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorMessage.style.display = 'none';
        successMessage.style.display = 'none';

        if (!awaitingOtp) {
          // Step 1: Request OTP
          loginBtn.disabled = true;
          loginBtn.innerHTML = '<span class="spinner"></span> Requesting OTP...';
          const fd = new FormData(form);
          try {
            const res = await fetch('../includes/request_otp.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
              awaitingOtp = true;
              otpSection.style.display = 'block';
              otpHint.textContent = `OTP sent to ${data.email}.`;
              loginBtn.disabled = false;
              loginBtn.innerHTML = '<i class="fas fa-check"></i> Verify OTP & Login';
              if (otpInputs[0]) otpInputs[0].focus();
            } else {
              resetLoginButton();
              errorText.textContent = data.message || 'Failed to request OTP';
              errorMessage.style.display = 'block';
            }
          } catch (err) {
            resetLoginButton();
            errorText.textContent = 'Network error. Please try again.';
            errorMessage.style.display = 'block';
          }
        } else {
          // Step 2: Verify OTP
          const code = otpInputs.map(i => (i ? i.value : '')).join('');
          if (code.length !== 6) {
            errorText.textContent = 'Please enter the 6-digit OTP.';
            errorMessage.style.display = 'block';
            return;
          }
          loginBtn.disabled = true;
          loginBtn.innerHTML = '<span class="spinner"></span> Verifying...';
          const fd = new FormData(form);
          try {
            const res = await fetch('../includes/verify_otp_ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
              successText.textContent = 'OTP verified! Redirecting...';
              successMessage.style.display = 'block';
              loginBtn.innerHTML = '<i class="fas fa-check"></i> Verified';
              setTimeout(() => { window.location.href = data.redirect; }, 800);
            } else {
              loginBtn.disabled = false;
              loginBtn.innerHTML = '<i class="fas fa-check"></i> Verify OTP & Login';
              errorText.textContent = data.message || 'Invalid OTP';
              errorMessage.style.display = 'block';
            }
          } catch (err) {
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-check"></i> Verify OTP & Login';
            errorText.textContent = 'Network error. Please try again.';
            errorMessage.style.display = 'block';
          }
        }
      });
        
      // THE JAVASCRIPT BLOCK FOR THE PASSWORD TOGGLE BUTTON HAS BEEN REMOVED.
        
    });
  </script>
  <?php if (!empty($_SESSION['error'])): ?>
  <script>
    document.getElementById('errorText').textContent = <?php echo json_encode($_SESSION['error']); ?>;
    document.getElementById('errorMessage').style.display = 'block';
  </script>
  <?php unset($_SESSION['error']); endif; ?>
</body>
</html>
