<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Faculty Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
        --primary-color: #2563eb;
        --primary-dark: #1d4ed8;
        --primary-light: #3b82f6;
        --secondary-color: #64748b;
        --success-color: #059669;
        --warning-color: #d97706;
        --danger-color: #dc2626;
        --gray-50: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e1;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1e293b;
        --gray-900: #0f172a;
        --white: #ffffff;
        --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--gray-50);
      color: var(--gray-900);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      line-height: 1.6;
      font-weight: 400;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* MODERN HEADER */
    .header {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(8px);
        background-color: rgba(255, 255, 255, 0.95);
    }

    .header-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 4rem;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--gray-900);
        text-decoration: none;
    }

    .logo-icon {
        width: 2rem;
        height: 2rem;
        background: var(--primary-color);
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
    }

    .nav-menu {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        font-size: 0.875rem;
        color: var(--gray-600);
        text-decoration: none;
        transition: all 0.15s ease;
        position: relative;
    }

    .nav-link:hover {
        background-color: var(--gray-100);
        color: var(--gray-900);
    }

    .nav-link.active {
        background-color: var(--primary-color);
        color: var(--white);
    }

    .nav-link.danger {
        color: var(--danger-color);
    }

    .nav-link.danger:hover {
        background-color: rgba(220, 38, 38, 0.1);
        color: var(--danger-color);
    }

    .mobile-menu-button {
        display: none;
        background: none;
        border: none;
        padding: 0.5rem;
        color: var(--gray-600);
        border-radius: 0.375rem;
        cursor: pointer;
    }

    .mobile-menu-button:hover {
        background-color: var(--gray-100);
        color: var(--gray-900);
    }

    /* MAIN CONTENT */
    .main-content {
      flex: 1;
      max-width: 1280px;
      width: 100%;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }

    .welcome-section {
      margin-bottom: 2rem;
    }

    .welcome-title {
      font-size: 1.875rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
      color: var(--gray-600);
      font-size: 1rem;
    }

    /* PROFILE CARD */
    .profile-card {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
      border-radius: 1rem;
      padding: 2rem;
      margin-bottom: 2rem;
      text-align: center;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .profile-card::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      pointer-events: none;
    }

    .profile-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      margin: 0 auto 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 600;
      border: 3px solid rgba(255, 255, 255, 0.3);
    }

    .profile-name {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .profile-role {
      font-size: 0.875rem;
      opacity: 0.9;
      margin-bottom: 1rem;
    }

    .profile-status {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(34, 197, 94, 0.2);
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .status-dot {
      width: 6px;
      height: 6px;
      background: #22c55e;
      border-radius: 50%;
    }

    /* INFO GRID */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .info-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      border: 1px solid var(--gray-200);
      box-shadow: var(--shadow-sm);
    }

    .info-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.025em;
      color: var(--gray-500);
      margin-bottom: 0.5rem;
    }

    .info-value {
      font-size: 1rem;
      font-weight: 600;
      color: var(--gray-900);
    }

    .info-badge {
      display: inline-block;
      background: #dbeafe;
      color: var(--primary-dark);
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    /* ACTION CARDS */
    .actions-section {
      margin-bottom: 2rem;
    }

    .section-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 1rem;
    }

    .action-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .action-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      border: 1px solid var(--gray-200);
      box-shadow: var(--shadow-sm);
      transition: all 0.2s ease;
      text-align: center;
    }

    .action-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: 0.75rem;
      margin: 0 auto 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .action-card:nth-child(1) .action-icon { 
      background: #dbeafe; 
      color: var(--primary-dark); 
    }
    .action-card:nth-child(2) .action-icon { 
      background: #d1fae5; 
      color: var(--success-color); 
    }
    .action-card:nth-child(3) .action-icon { 
      background: #fef3c7; 
      color: var(--warning-color); 
    }
    .action-card:nth-child(4) .action-icon { 
      background: #e0e7ff; 
      color: #6366f1; 
    }
    .action-card:nth-child(5) .action-icon { 
      background: #fce7f3; 
      color: #ec4899; 
    }
    .action-card:nth-child(6) .action-icon { 
      background: #fee2e2; 
      color: var(--danger-color); 
    }

    .action-title {
      font-size: 1rem;
      font-weight: 600;
      color: var(--gray-900);
      margin-bottom: 0.5rem;
    }

    .action-description {
      font-size: 0.875rem;
      color: var(--gray-600);
      margin-bottom: 1rem;
    }

    .action-button {
      background: var(--primary-color);
      color: white;
      border: none;
      padding: 0.625rem 1.25rem;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s ease;
      text-decoration: none;
      display: inline-block;
    }

    .action-button:hover {
      background: var(--primary-dark);
    }

    .logout-btn {
      background: var(--danger-color) !important;
    }

    .logout-btn:hover {
      background: #b91c1c !important;
    }

    /* QUICK LINKS */
    .quick-links {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      border: 1px solid var(--gray-200);
      box-shadow: var(--shadow-sm);
    }

    .quick-links-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 0.75rem;
    }

    .quick-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      border-radius: 0.5rem;
      text-decoration: none;
      color: var(--gray-700);
      transition: all 0.2s ease;
      border: 1px solid transparent;
    }

    .quick-link:hover {
      background: var(--gray-50);
      border-color: var(--gray-200);
    }

    .quick-link-icon {
      font-size: 1.25rem;
    }

    .quick-link-text {
      font-weight: 500;
      font-size: 0.875rem;
    }

    /* FOOTER */
    .footer {
      background: var(--white);
      color: var(--gray-600);
      font-size: 0.875rem;
      text-align: center;
      padding: 2rem 1.5rem;
      border-top: 1px solid var(--gray-200);
      margin-top: auto;
    }

    .footer-content {
      max-width: 1280px;
      margin: 0 auto;
    }

    .footer-text {
      margin-bottom: 0.5rem;
    }

    .footer-subtext {
      font-size: 0.75rem;
      opacity: 0.8;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .header-container {
          padding: 0 1rem;
      }

      .nav-menu {
          display: none;
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background: var(--white);
          border-top: 1px solid var(--gray-200);
          flex-direction: column;
          padding: 1rem;
          gap: 0.25rem;
          box-shadow: var(--shadow-md);
      }

      .nav-menu.mobile-open {
          display: flex;
      }

      .mobile-menu-button {
          display: block;
      }

      .main-content {
        padding: 1rem 0.75rem;
      }
      
      .welcome-section {
        margin-bottom: 1.5rem;
      }

      .welcome-title {
        font-size: 1.5rem;
      }

      .welcome-subtitle {
        font-size: 0.875rem;
      }
      
      .profile-card {
        padding: 1.5rem 1rem;
        margin-bottom: 1.5rem;
      }

      .profile-avatar {
        width: 64px;
        height: 64px;
        font-size: 1.5rem;
      }

      .profile-name {
        font-size: 1.25rem;
      }
      
      .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .info-card {
        padding: 1rem;
      }
      
      .action-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .action-card {
        padding: 1.25rem;
      }

      .action-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
      }
      
      .quick-links-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }

      .quick-links {
        padding: 1rem;
      }

      .section-title {
        font-size: 1.125rem;
      }

      .footer {
        padding: 1.5rem 0.75rem;
      }

      .footer-text {
        font-size: 0.75rem;
      }

      .footer-subtext {
        font-size: 0.625rem;
      }
    }

    /* Focus States */
    .nav-link:focus,
    .action-button:focus,
    .quick-link:focus {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    /* Accessibility */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
  </style>
</head>
<body>

  <!-- MODERN HEADER -->
  <header class="header">
    <div class="header-container">
        <a href="dashboard.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <span>Faculty Portal</span>
        </a>
        
        <button class="mobile-menu-button" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav class="nav-menu" id="navMenu">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../includes/logout.php" class="nav-link danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    
    <!-- Welcome Section -->
    <div class="welcome-section">
      <h1 class="welcome-title">Faculty Dashboard</h1>
      <p class="welcome-subtitle">Welcome back! Here's an overview of your feedback management activities.</p>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
      <div class="profile-avatar">
        <i class="fas fa-user-tie"></i>
      </div>
      <div class="profile-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Dr. Faculty Member') ?></div>
      <div class="profile-role">Faculty Member - <?= htmlspecialchars($_SESSION['department'] ?? 'Academic Department') ?></div>
      <div class="profile-status">
        <div class="status-dot"></div>
        Active
      </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">Faculty ID</div>
        <div class="info-value"><?= htmlspecialchars($_SESSION['faculty_id'] ?? $_SESSION['user_id'] ?? 'FAC001') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Department</div>
        <div class="info-value"><?= htmlspecialchars($_SESSION['department'] ?? 'Academic Department') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">User Role</div>
        <div class="info-value">
          <span class="info-badge">Faculty</span>
        </div>
      </div>
      <div class="info-card">
        <div class="info-label">Session Status</div>
        <div class="info-value">Active</div>
      </div>
    </div>

    <!-- Action Cards -->
    <div class="actions-section">
      <h2 class="section-title">Quick Actions</h2>
      <div class="action-grid">
        <div class="action-card">
          <div class="action-icon">
            <i class="fas fa-chart-bar"></i>
          </div>
          <div class="action-title">View Feedback Reports</div>
          <div class="action-description">Access comprehensive feedback reports and analytics for your courses.</div>
          <a href="view_feedback.php" class="action-button">
            <i class="fas fa-chart-line"></i> View Reports
          </a>
        </div>
        
        <div class="action-card">
          <div class="action-icon">
            <i class="fas fa-sign-out-alt"></i>
          </div>
          <div class="action-title">System Logout</div>
          <div class="action-description">Safely log out of the faculty portal and end your current session.</div>
          <a href="../includes/logout.php" class="action-button logout-btn">
            <i class="fas fa-power-off"></i> Logout
          </a>
        </div>
      </div>
    </div>

    
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-content">
      <div class="footer-text">&copy; <?php echo date('Y'); ?> College Feedback Portal. All rights reserved.</div>
      <div class="footer-subtext">Enhancing education through continuous feedback and improvement</div>
    </div>
  </footer>

</body>

<script>
// Mobile menu toggle
function toggleMobileMenu() {
    const navMenu = document.getElementById('navMenu');
    navMenu.classList.toggle('mobile-open');
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const navMenu = document.getElementById('navMenu');
    const mobileButton = document.querySelector('.mobile-menu-button');
    
    if (!navMenu.contains(event.target) && !mobileButton.contains(event.target)) {
        navMenu.classList.remove('mobile-open');
    }
});

// Responsive navigation
window.addEventListener('resize', function() {
    const navMenu = document.getElementById('navMenu');
    if (window.innerWidth > 768) {
        navMenu.classList.remove('mobile-open');
    }
});

// Loading state for buttons
document.querySelectorAll('.action-button, .quick-link').forEach(button => {
    button.addEventListener('click', function(e) {
        if (this.href && !this.href.includes('#') && !this.href.includes('javascript:')) {
            const icon = this.querySelector('i');
            
            if (icon && !icon.classList.contains('fa-spin')) {
                const originalClass = icon.className;
                icon.className = 'fas fa-spinner fa-spin';
                
                // Reset after timeout (in case navigation fails)
                setTimeout(() => {
                    icon.className = originalClass;
                }, 3000);
            }
        }
    });
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.info-card, .action-card, .profile-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

</html>