<?php
// Left panel: Login form (scoped for /login/index.php)
?>
<div class="left-panel">
  <div class="form-header">
    <h1 class="form-title">Hello!</h1>
    <p class="form-subtitle">Sign in to your account</p>
  </div>

  <form method="post" id="loginForm" autocomplete="off">
    <!-- Action is removed because JavaScript handles the form submission -->

    <div class="form-group">
      <label for="username" class="form-label">Username</label>
      <div class="input-group">
        <i class="fas fa-user input-icon"></i>
        <input type="text" id="username" name="username" class="form-control" placeholder="Username" required>
      </div>
    </div>

    <!-- The password field has been completely removed from this file. -->

    <div class="form-group">
      <label for="role" class="form-label">User Role</label>
      <div class="select-wrapper">
        <select name="role" id="role" class="form-control" required>
          <option value="">Select your role</option>
          <option value="admin">Administrator</option>
          <option value="student">Student</option>
          <option value="faculty">Faculty Member</option>
        </select>
      </div>
    </div>

    <!-- This section is hidden until the user is found -->
    <div id="otpSection" style="display:none; margin-top: 1.5rem;">
      <label class="form-label">Enter OTP</label>
      <div id="otpHint" style="color:#6b7280; font-size:.9rem; margin-bottom:.5rem;"></div>
      <div style="display:flex; gap:.5rem; justify-content: center;">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d1" name="d1">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d2" name="d2">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d3" name="d3">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d4" name="d4">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d5" name="d5">
        <input type="text" maxlength="1" class="form-control otp-input" style="width:44px; text-align:center; padding-left: 0.75rem;" id="d6" name="d6">
      </div>
    </div>

    <button type="submit" class="login-btn" id="loginBtn">
      <i class="fas fa-paper-plane"></i>
      Send OTP
    </button>
  </form>

  <!-- Error/Success Messages -->
  <div class="error-message" id="errorMessage" style="display: none;">
    <i class="fas fa-exclamation-triangle"></i>
    <span id="errorText"></span>
  </div>
  <div class="success-message" id="successMessage" style="display: none;">
    <i class="fas fa-check-circle"></i>
    <span id="successText"></span>
  </div>
</div>
