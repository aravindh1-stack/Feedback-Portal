<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login/index.php');
    exit;
}

// Include the main header file (opens <html>, <head>, and <body>)
include __DIR__ . '/includes/header.php';

// Include the sidebar component
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content-wrapper" style="margin-left: 280px; flex: 1; display: flex; flex-direction: column; min-height: 100vh;">
    <header style="background-color: #FFFFFF; border-bottom: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: flex-end; padding: 1rem 2rem; position: sticky; top: 0; z-index: 20; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button style="background-color: #FFFFFF; border: 1px solid #E2E8F0; color: #64748B; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;">
                <i class="fas fa-bell"></i>
            </button>
            <button id="themeToggle" style="background-color: #FFFFFF; border: 1px solid #E2E8F0; color: #64748B; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;">
                <i class="fas fa-sun"></i>
            </button>
            <div style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' fill='%233C50E0'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='white' font-size='16' font-weight='bold'%3EA%3C/text%3E%3C/svg%3E" 
                     alt="Admin" 
                     style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #E2E8F0;" />
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></span>
                    <span style="color: #64748B; font-size: 0.8rem;">Administrator</span>
                </div>
                <i class="fas fa-chevron-down" style="margin-left: 0.5rem; font-size: 0.8rem; color: #64748B;"></i>
            </div>
        </div>
    </header>
  
    <main style="padding: 2rem; max-width: 1400px; margin: 0 auto; width: 100%; flex-grow: 1;">
        <?php
        // Include all modules
        include __DIR__ . '/modules/welcome_section.php';
        include __DIR__ . '/modules/status_grid.php';
        include __DIR__ . '/modules/kpi_cards.php';
        include __DIR__ . '/modules/quick_actions.php';
        include __DIR__ . '/modules/additional_actions.php';
        include __DIR__ . '/modules/recent_data_section.php';
        ?>
    </main>
  
    <?php
    // Include the footer component
    include __DIR__ . '/includes/footer.php';
    ?>
</div>

</body>
</html>