<?php
// Common visible top bar for admin pages
// Usage:
//   $page_heading = 'Dashboard';
//   $show_theme_toggle = true; // only on dashboard
//   $show_search = true;       // optional search box on the right
// Variables defaulting
if (!isset($page_heading) || $page_heading === '') { $page_heading = 'Dashboard'; }
if (!isset($show_theme_toggle)) { $show_theme_toggle = false; }
if (!isset($show_search)) { $show_search = false; }
?>
<header class="header">
  <div class="header-left">
    <button class="header-btn sidebar-toggle" style="display: none;" onclick="typeof toggleSidebar==='function'&&toggleSidebar()">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
      </svg>
    </button>
    <div>
      <h1 class="page-title"><?php echo htmlspecialchars($page_heading); ?></h1>
    </div>
  </div>
  <div class="header-right">
    <?php if ($show_search): ?>
      <div class="header-search">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 103.75 3.75a7.5 7.5 0 0012.9 12.9z" />
        </svg>
        <input type="text" class="search-input" placeholder="Search anything...">
      </div>
    <?php endif; ?>
    <div class="header-actions">
      <?php if ($show_theme_toggle): ?>
        <button id="themeToggle" class="header-btn" data-tooltip="Toggle Dark Mode">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0112 21.75c-5.385 0-9.75-4.365-9.75-9.75 0-4.146 2.652-7.675 6.36-9.045a.75.75 0 01.964.966A7.5 7.5 0 0019.5 15.39a.75.75 0 01.966.963c-.209.534-.455 1.046-.714 1.55z" />
          </svg>
        </button>
      <?php endif; ?>
      <button class="header-btn" data-tooltip="Notifications">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 10-12 0v.75a8.967 8.967 0 01-2.311 6.022c1.78.64 3.607 1.085 5.454 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <span class="notification-badge">3</span>
      </button>
      <div class="user-menu">
        <div class="user-avatar" data-tooltip="Administrator">A</div>
      </div>
    </div>
  </div>
</header>
