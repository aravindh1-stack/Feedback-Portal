<?php
// Redirect root to the new Login page
header('Location: login/index.php');
exit;
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f3e8ff; /* soft lilac */
            color: #1f2937;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Styles */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: #4f46e5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .brand-text {
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header-info {
            background: #f9fafb;
            color: #6b7280;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e5e7eb;
        }

        /* Main Container */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        .login-container {
            max-width: 900px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(79, 70, 229, 0.15);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        /* Left Side - Login Form */
        .left-panel {
            background: #ffffff;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1f2937;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control:hover {
            border-color: #9ca3af;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }

        select.form-control {
            appearance: none;
            background-image: none;
            cursor: pointer;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 1;
        }

        .input-group .form-control {
            padding-left: 2.75rem;
        }

        .login-btn {
            background: #4f46e5;
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }

        .login-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: center;
            font-weight: 500;
            border: 1px solid #fecaca;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .success-message {
            background: #f0fdf4;
            color: #15803d;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: center;
            font-weight: 500;
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Right Side - Visual Panel */
        .visual-panel {
            position: relative;
            background: radial-gradient(1200px 600px at -10% -20%, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0) 50%),
                        linear-gradient(135deg, #6d28d9 0%, #7c3aed 35%, #4f46e5 100%);
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .visual-card {
            width: 85%;
            max-width: 360px;
            aspect-ratio: 1 / 1;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 24px;
            backdrop-filter: blur(6px);
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 20px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .visual-card img{
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Social buttons */
        .social-login {
            margin-top: 1.5rem;
        }

        .social-title {
            margin: 1rem 0 0.75rem;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
        }

        .social-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            color: #374151;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .social-btn + .social-btn { margin-top: 0.75rem; }

        .social-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 16px rgba(0,0,0,.06); }

        .social-btn.google i { color: #ea4335; }

        .social-btn.facebook i { color: #1877f2; }

        /* Footer */
        .footer {
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 2rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                margin: 1rem;
            }

            .visual-panel { order: 2; min-height: 260px; }

            .left-panel { order: 1; padding: 2rem; }

            .form-title {
                font-size: 1.75rem;
            }

            .header-content {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .brand-text {
                font-size: 1.25rem;
            }

            .main-container {
                padding: 2rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .login-form, .info-panel {
                padding: 1.5rem;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .main-container {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Form Validation States */
        .form-control.invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-control.valid {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Accessibility Improvements */
        .form-control:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
        }

        .login-btn:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.5);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Main Container -->
    <main class="main-container">
        <div class="login-container">
            <?php include __DIR__ . '/includes/login_form.php'; ?>
            <?php include __DIR__ . '/includes/welcome.php'; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const formControls = document.querySelectorAll('.form-control');

            // Form submission handling
            form.addEventListener('submit', function(e) {
                // Show loading state
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="spinner"></span> Signing In...';
                
                // Hide any previous messages
                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';
                
                // Remove validation classes
                formControls.forEach(control => {
                    control.classList.remove('invalid', 'valid');
                });

                // Note: In a real implementation, form would submit to server
                // This is just for demonstration purposes
            });

            // Form validation
            formControls.forEach(control => {
                control.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('invalid');
                        this.classList.remove('valid');
                    } else if (this.value.trim()) {
                        this.classList.add('valid');
                        this.classList.remove('invalid');
                    }
                });

                control.addEventListener('input', function() {
                    if (this.classList.contains('invalid') && this.value.trim()) {
                        this.classList.remove('invalid');
                        this.classList.add('valid');
                    }
                });
            });

            // Role selection enhancement
            const roleSelect = document.getElementById('role');
            roleSelect.addEventListener('change', function() {
                if (this.value) {
                    this.classList.add('valid');
                    this.classList.remove('invalid');
                }
            });

            // Password visibility toggle (optional enhancement)
            const passwordField = document.getElementById('password');
            const passwordGroup = passwordField.parentElement;
            
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.style.cssText = `
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                z-index: 2;
            `;
            
            toggleBtn.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            passwordGroup.appendChild(toggleBtn);

            // Keyboard navigation improvements
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    const formElements = Array.from(form.elements);
                    const currentIndex = formElements.indexOf(e.target);
                    const nextElement = formElements[currentIndex + 1];
                    
                    if (nextElement && nextElement.type !== 'submit') {
                        nextElement.focus();
                    } else {
                        loginBtn.click();
                    }
                }
            });
        });

        // Simulate server response (for demonstration)
        // In real implementation, this would be handled by the server
        function simulateLogin(success = false) {
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const loginBtn = document.getElementById('loginBtn');

            setTimeout(() => {
                loginBtn.disabled = false;
                
                if (success) {
                    successMessage.style.display = 'flex';
                    loginBtn.innerHTML = '<i class="fas fa-check"></i> Success!';
                    setTimeout(() => {
                        // Redirect would happen here
                        console.log('Redirecting to dashboard...');
                    }, 1500);
                } else {
                    errorMessage.style.display = 'flex';
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
                }
            }, 2000);
        }
    </script>
</body>
</html>