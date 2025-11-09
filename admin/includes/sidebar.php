<?php
// Sidebar file - no session_start() needed here
?>
<aside class="sidebar">
    <style>
        /* === NEW UNIQUE & PROFESSIONAL SIDEBAR UI === */
        
        /* * Main file-la irundhu --primary-blue, etc. varum
        */

        /* --- 1. DARK THEME CONTAINER (DEFAULT) --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #1f2937; /* Professional Dark Gray */
            color: var(--text-muted);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; height: 100vh;
            z-index: 30;
            border-right: 1px solid #374151; /* Dark border */
            transition: width 0.3s ease; /* Width change-ku animation */
        }
        
        /* Ippo main content-um sidebar collapse-ku react pannum */
        /* Intha CSS unga dashboard.php, manage_users.php-layum work aagum */
        .main-content {
            transition: margin-left 0.3s ease;
        }
        body.sidebar-collapsed .main-content {
            margin-left: 92px; /* Collapsed width */
        }

        /* --- 2. UNIQUE LOGO & TOGGLE SECTION --- */
        .sidebar-logo-header {
            display: flex;
            align-items: center;
            justify-content: space-between; /* Logo-vum button-um piriyum */
            padding: 0.5rem 0.5rem 1.5rem 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #374151; /* The "Line" */
            flex-shrink: 0;
            overflow: hidden; /* Collapse aagum bothu neat-a irukka */
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .logo-icon {
            width: 40px; height: 40px;
            border-radius: var(--radius-md);
            background: var(--primary-purple);
            display: grid; place-items: center;
            color: var(--text-light);
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .logo-text .title {
            font-size: 1.125rem; font-weight: 700;
            color: var(--text-light);
            white-space: nowrap; /* Text odhunga koodadhu */
        }
        .logo-text .subtitle {
            font-size: 0.8rem; color: var(--text-muted);
            white-space: nowrap;
        }

        /* UNIQUE: The Toggle Button */
        .sidebar-toggle {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border: none;
            display: grid; place-items: center;
            cursor: pointer;
            font-size: 1rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .sidebar-toggle:hover {
            background: var(--primary-blue);
        }
        .sidebar-toggle i {
             transition: transform 0.3s ease;
        }

        /* --- 3. NAVIGATION SECTION --- */
        .sidebar-nav-container {
            flex-grow: 1;
            overflow-y: auto; overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #4b5563 #1f2937;
        }
        /* ... (scrollbar styles - unchanged) ... */

        .sidebar-nav {
            display: flex; flex-direction: column;
            gap: 0.375rem; /* 6px */
        }
        
        .nav-section-title {
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.75rem 0.75rem 0.25rem;
            color: #6b7280;
            white-space: nowrap;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.875rem; /* 14px */
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            outline: none;
            position: relative;
            white-space: nowrap; /* Text hide aaga idhu mukkiyam */
            overflow: hidden;
        }
        .nav-link i {
            width: 20px; text-align: center;
            font-size: 1rem;
            color: #9ca3af;
            transition: color 0.2s ease;
            flex-shrink: 0; /* Icon eppovum correct size la irukkum */
        }
        .nav-link span {
            transition: opacity 0.2s ease;
        }

        /* Neat Hover */
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
        }
        .nav-link:hover i { color: var(--text-light); }
        
        /* "DIFFERENT" ACTIVE LINE (Rounded Bar) */
        .nav-link.is-active {
            background-color: rgba(59, 130, 246, 0.1); /* Faded blue */
            color: #ffffff;
            font-weight: 600;
        }
        .nav-link.is-active i { color: #ffffff; }

        /* Ithaan antha puthu rounded bar */
        .nav-link.is-active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px; /* Line height */
            background-color: var(--primary-blue);
            border-radius: 0 4px 4px 0; /* Pill shape */
        }

        /* --- 4. FOOTER / PROFILE SECTION --- */
        .sidebar-footer {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #374151;
            flex-shrink: 0;
            overflow: hidden; /* Collapse aagum bothu neat-a irukka */
        }
        
        .logout-link {
            display: flex; align-items: center;
            gap: 0.875rem; padding: 0.75rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        /* ... (logout styles - unchanged) ... */
        .logout-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--primary-purple);
            color: var(--text-light);
            display: grid; place-items: center;
            font-size: 0.9rem; font-weight: 600;
            flex-shrink: 0;
        }
        .logout-info { flex-grow: 1; white-space: nowrap; }
        .logout-info .name { font-weight: 600; font-size: 0.9rem; color: var(--text-light); }
        .logout-info .role { font-size: 0.8rem; color: var(--text-muted); }
        .logout-icon { font-size: 1.1rem; color: var(--text-muted); }
        .logout-link:hover { background-color: rgba(255, 255, 255, 0.05); }
        .logout-link:hover .logout-icon { color: #f87171; }
        
        
        /* --- 5. STYLES FOR COLLAPSED SIDEBAR --- */
        body.sidebar-collapsed .sidebar {
            width: 92px; /* Puthu chinna width */
            padding: 1.5rem 0.75rem;
        }
        body.sidebar-collapsed .sidebar-logo-header {
            justify-content: center; /* Logo-va center pannidum */
            border-bottom: none; /* Chinna sidebar-la line venam */
        }
        /* Marachuruvom (Hide panna vendiyavai) */
        body.sidebar-collapsed .logo-text,
        body.sidebar-collapsed .sidebar-toggle,
        body.sidebar-collapsed .nav-link span,
        body.sidebar-collapsed .nav-section-title,
        body.sidebar-collapsed .logout-info,
        body.sidebar-collapsed .logout-icon {
            display: none;
            opacity: 0;
        }
        body.sidebar-collapsed .sidebar-toggle i {
             transform: rotate(180deg); /* Button icon-a thiruppurom */
        }
        /* Ippo toggle button header-la irukkum */
        .header .sidebar-toggle {
            display: none; /* Perusa irukkum bothu header-la venam */
        }
        
        /* Collapsed state-la link-a center pannurom */
        body.sidebar-collapsed .nav-link {
            justify-content: center;
            padding: 0.75rem;
        }
        body.sidebar-collapsed .nav-link.is-active::before {
            height: 6px; /* Active line-a oru dot maathiri maathrom */
            width: 6px;
            border-radius: 50%;
            left: 6px; /* Konjam thalli vekkrom */
        }
        body.sidebar-collapsed .logout-link {
            justify-content: center;
        }

        /* --- 6. LIGHT THEME (WHEN TOGGLED) --- */
        
        body:not(.dark-theme) .sidebar {
            background-color: #f8f9fa; /* "Dim White" */
            color: var(--text-body);
            border-right: 1px solid var(--border-color);
        }
        body:not(.dark-theme) .sidebar-logo-header {
            border-bottom: 1px solid var(--border-color); /* Light line */
        }
        body:not(.dark-theme) .logo-text .title { color: var(--text-dark); }
        body:not(.dark-theme) .logo-text .subtitle { color: var(--text-body); }
        body:not(.dark-theme) .sidebar-toggle {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-body);
        }
        body:not(.dark-theme) .sidebar-toggle:hover {
            background: var(--primary-blue);
            color: var(--text-light);
        }
        body:not(.dark-theme) .nav-section-title { color: var(--text-muted); }
        body:not(.dark-theme) .nav-link { color: #334155; }
        body:not(.dark-theme) .nav-link i { color: var(--text-muted); }

        /* Light Hover */
        body:not(.dark-theme) .nav-link:hover {
            background-color: #f1f5f9; /* Subtle gray */
            color: var(--text-dark);
        }
        body:not(.dark-theme) .nav-link:hover i { color: var(--text-dark); }

        /* Light "Line" Active State */
        body:not(.dark-theme) .nav-link.is-active {
            background-color: var(--info-bg); /* Light blue */
            color: var(--primary-blue);
            font-weight: 600;
        }
        body:not(.dark-theme) .nav-link.is-active i { color: var(--primary-blue); }
        /* Light active line-um blue thaan */
        body:not(.dark-theme) .nav-link.is-active::before {
             background-color: var(--primary-blue);
        }
        
        /* Light Footer */
        body:not(.dark-theme) .sidebar-footer { border-top: 1px solid var(--border-color); }
        body:not(.dark-theme) .logout-link:hover { background-color: #f1f5f9; }
        body:not(.dark-theme) .logout-info .name { color: var(--text-dark); }
        body:not(.dark-theme) .logout-info .role { color: var(--text-muted); }
        body:not(.dark-theme) .logout-icon { color: var(--text-muted); }
        body:not(.dark-theme) .logout-link:hover .logout-icon { color: var(--danger-text); }

    </style>
    
    <div class="sidebar-logo-header">
        <a href="dashboard.php" class="sidebar-logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i> 
            </div>
            <div class="logo-text">
                <div class="title">Aarasys</div>
                <div class="subtitle">Admin Portal</div>
            </div>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-angle-left"></i>
        </button>
    </div>

    <div class="sidebar-nav-container">
        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            
            <a href="dashboard.php" data-nav="true" class="nav-link"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="manage_users.php" data-nav="true" class="nav-link"><i class="fas fa-users"></i><span>Manage Users</span></a>
            <a href="create_feedback_form.php" data-nav="true" class="nav-link"><i class="fas fa-edit"></i><span>Create Forms</span></a>
            <a href="manage_forms.php" data-nav="true" class="nav-link"><i class="fas fa-clipboard-list"></i><span>Manage Forms</span></a>
            <a href="view_feedback.php" data-nav="true" class="nav-link"><i class="fas fa-chart-bar"></i><span>View Feedback</span></a>
            <a href="student_feedback_list.php" data-nav="true" class="nav-link"><i class="fas fa-user-graduate"></i><span>Student Feedback</span></a>
            <a href="chief_guest_feedback.php" data-nav="true" class="nav-link"><i class="fas fa-star"></i><span>Chief Guest</span></a>
            <a href="chief_guest_feedback_report.php" data-nav="true" class="nav-link"><i class="fas fa-file-invoice"></i><span>Guest Reports</span></a>
        </nav>
    </div>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-link" title="Logout">
            <div class="logout-avatar">AD</div>
            <div class="logout-info">
                <div class="name">Admin</div>
                <div class="role">Administrator</div>
            </div>
            <i class="fas fa-sign-out-alt logout-icon"></i>
        </a>
    </div>

</aside>

<script>
(function(){
    // Active Link Script (idhu correct-a work aagum)
    try {
        var currentPath = location.pathname.replace(/\/$/, '');
        if (currentPath === "") { currentPath = "/"; }

        document.querySelectorAll('.sidebar-nav [data-nav="true"]').forEach(function(a) {
            if (a.href) {
                var linkPath = new URL(a.href, location.href).pathname.replace(/\/$/, '');
                if (linkPath === "") { linkPath = "/"; }
                
                if (currentPath.endsWith(linkPath) && linkPath !== "/") {
                    a.classList.add('is-active');
                } else if (currentPath === linkPath && linkPath === "/") {
                     a.classList.add('is-active');
                }
            }
        });
    } catch(e) {
        console.error('Sidebar active-link script error:', e);
    }

    // PUTHU SCRIPT: Sidebar Collapse Toggle
    try {
        const toggleButton = document.getElementById('sidebarToggle');
        const toggleIcon = toggleButton.querySelector('i');
        
        toggleButton.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            
            // Icon-a maathuvom
            if (document.body.classList.contains('sidebar-collapsed')) {
                toggleIcon.classList.remove('fa-angle-left');
                toggleIcon.classList.add('fa-angle-right');
            } else {
                toggleIcon.classList.remove('fa-angle-right');
                toggleIcon.classList.add('fa-angle-left');
            }
        });
    } catch(e) {
        console.error('Sidebar toggle script error:', e);
    }
})();
</script>