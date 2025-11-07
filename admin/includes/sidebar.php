<aside id="sidebar" style="width: 280px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 30; padding: 1.5rem;">
  <style>
    /* * ======================================
     * STYLES FOR THE DARK THEME SIDEBAR
     * ======================================
     */
    
    /* General Sidebar (inherits global theme vars from dashboard) */
    #sidebar {
      color: var(--text-color);
      background-color: var(--sidebar-bg);
      border-right: 1px solid var(--border-color);
      box-shadow: 4px 0 12px rgba(0,0,0,0.05);
      overflow-y: auto; 
    }
    
    /* Section Titles (e.g., "MENU") */
    #sidebar .section-title { 
      font-size: 11px; 
      letter-spacing: 0.08em; 
      color: var(--gray-400);
      font-weight: 700; 
      padding: 8px 10px; 
      text-transform: uppercase; 
    }
    
    /* Navigation List */
    #sidebar .nav-list { 
      display: flex; 
      flex-direction: column; 
      gap: 4px; 
    }
    
    /* Navigation Links */
    #sidebar .nav-link { 
      display: flex; 
      align-items: center; 
      gap: 12px; 
      padding: 10px 12px;
      margin: 0 4px;
      border-radius: 8px; 
      text-decoration: none; 
      color: var(--text-color);
      font-weight: 500;
      transition: background-color .15s ease, color .15s ease; 
    }
    
    /* Link Icons */
    #sidebar .nav-link i { 
      width: 20px; 
      text-align: center; 
      opacity: .75; 
      font-size: 16px; 
    }
    
    /* Link Hover State */
    #sidebar .nav-link:hover { 
      background-color: var(--primary-50);
      color: var(--primary-600);
      text-decoration: none; 
    }
    body.dark-theme #sidebar .nav-link:hover { 
      background-color: rgba(59,130,246,0.1);
      color: var(--primary-500);
    }
    
    /* --- ACTIVE STATE --- */
    #sidebar .nav-link.is-active { 
      background-color: var(--primary-50);
      color: var(--primary-600);
      font-weight: 700; 
      text-decoration: none; 
    }
    body.dark-theme #sidebar .nav-link.is-active { 
      background-color: rgba(59,130,246,0.1);
      color: var(--primary-500);
    }
    #sidebar .nav-link.is-active i {
      opacity: 1;
    }

    /* Reports Toggle Button */
    #sidebar .nav-toggle { 
      display: flex; 
      align-items: center; 
      gap: 12px;
      padding: 10px 12px; 
      margin: 6px 4px 0; 
      border-radius: 8px; 
      background: transparent; 
      border: none; 
      color: var(--text-color); 
      cursor: pointer; 
      width: calc(100% - 8px); 
      text-align: left;
      font-size: 1rem;
      font-family: inherit;
      font-weight: 500;
      transition: background-color .15s ease, color .15s ease;
    }
    #sidebar .nav-toggle:hover {
      background-color: var(--primary-50);
      color: var(--primary-600);
    }
    body.dark-theme #sidebar .nav-toggle:hover { background-color: rgba(59,130,246,0.1); color: var(--primary-500); }
    #sidebar .nav-toggle i {
      width: 20px;
      text-align: center;
      opacity: .75;
      font-size: 16px;
    }
    
    /* Reports Sub-menu */
    #sidebar .nav-group { 
      display: none; 
      padding-left: 24px;
      border-left: 2px solid var(--border-color);
      margin: 4px 0 4px 22px;
    }
    #sidebar .nav-group .nav-link {
      padding-left: 8px;
    }

    /* Divider */
    #sidebar .divider { 
      height: 1px; 
      margin: 10px 6px; 
      background: var(--border-color);
    }
    
    /* --- LOGO --- */
    #sidebar .logo-wrapper {
      display:flex; 
      align-items:center; 
      gap:12px; 
      margin-bottom: 22px; 
      padding: 6px 8px;
    }
    #sidebar .logo-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-lg, 12px);
      background: linear-gradient(135deg, #6366F1, #8B5CF6);
      display: grid;
      place-items: center;
      box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.05));
      flex-shrink: 0;
    }
    #sidebar .logo-icon i {
      color: #fff;
      font-size: 18px;
      line-height: 1;
    }
    #sidebar .logo-text {
      line-height:1.1;
      /* Prevents text from wrapping */
      white-space: nowrap; 
      overflow: hidden;
    }
    #sidebar .logo-text .title {
      font-size: 16px; 
      font-weight: 800; 
      color: var(--heading-color);
      letter-spacing: 0.02em;
      margin-bottom: 2px;
    }
    #sidebar .logo-text .subtitle {
      font-size: 12px; 
      color: var(--gray-500);
    }
    
    /* --- LOGOUT BUTTON --- */
    #sidebar .logout-link {
      display:flex; 
      align-items:center; 
      gap:12px; 
      padding:10px 12px; 
      border-radius:10px; 
      text-decoration:none; 
      color: var(--danger-500);
      font-weight: 600; 
      margin-top: 14px;
      transition: background-color .15s ease, color .15s ease;
    }
    #sidebar .logout-link:hover {
      background-color: var(--danger-100);
      color: var(--danger-600);
    }
    body.dark-theme #sidebar .logout-link:hover { background-color: rgba(220,38,38,0.1); color: var(--danger-500); }
    #sidebar .logout-link i {
      width: 18px; 
      text-align: center; 
      font-size: 14px;
    }
  </style>
  
  <div class="logo-wrapper">
    <div class="logo-icon">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="logo-text">
      <div class="title">Aarasys</div>
      <div class="subtitle">Admin</div>
    </div>
  </div>
  <div class="section-title">MENU</div>
  <nav class="nav-list">
    <a href="../admin/dashboard.php" data-nav="true" class="nav-link"><i class="fas fa-home"></i><span>Dashboard</span></a>
    <a href="../admin/manage_users.php" data-nav="true" class="nav-link"><i class="fas fa-users"></i><span>Manage Users</span></a>
    <a href="../admin/create_feedback_form.php" data-nav="true" class="nav-link"><i class="fas fa-edit"></i><span>Create Forms</span></a>
    <a href="../admin/manage_forms.php" data-nav="true" class="nav-link"><i class="fas fa-clipboard-list"></i><span>Manage Forms</span></a>
    <a href="../admin/view_feedback.php" data-nav="true" class="nav-link"><i class="fas fa-chart-bar"></i><span>View Feedback</span></a>
    <a href="../admin/student_feedback_list.php" data-nav="true" class="nav-link"><i class="fas fa-user-graduate"></i><span>Student Feedback</span></a>
    <a href="../admin/chief_guest_feedback.php" data-nav="true" class="nav-link"><i class="fas fa-star"></i><span>Chief Guest</span></a>
    <a href="../admin/chief_guest_feedback_report.php" data-nav="true" class="nav-link"><i class="fas fa-file-invoice"></i><span>Guest Reports</span></a>
  </nav>

  <a href="../logout.php" class="logout-link">
    <i class="fas fa-sign-out-alt"></i>
    <span>Logout</span>
  </a>
</aside>

<script>
(function(){
  try{
    // Get the pathname (e.g., "/admin/dashboard.php") and remove any trailing slash
    var currentPath = location.pathname.replace(/\/$/, '');
    
    var reportsToggle = null;
    var reportsGroup = null;
    var activeInReports = false;

    document.querySelectorAll('#sidebar [data-nav=true]').forEach(function(a){
      // Use the anchor's absolute href to derive a clean pathname
      if (a.href) {
        var linkPath = new URL(a.href, location.href).pathname.replace(/\/$/, '');
        // Compare only pathnames
        if (linkPath === currentPath) {
          a.classList.add('is-active'); 
          if (reportsGroup && reportsGroup.contains(a)) {
            activeInReports = true;
          }
        }
      }
    });

    // Reports were removed; no toggle behavior needed
  }catch(e){
    console.error('Sidebar script error:', e);
  }
})();
</script>
