<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - College Feedback Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    /* Basic Reset & Font Import */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
      background-color: #F8FAFC;
      color: #0F172A;
      min-height: 100vh;
    }
    
    /* Dark Theme Styles */
    .dark-theme {
      --bg: #0f172a;
      --panel: #1e293b;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --border: #334155;
      --primary: #818cf8;
      --accent: #4ade80;
      --danger: #f87171;
      --warning: #fbbf24;
    }
    
    .dark-theme body {
      background-color: var(--bg);
      color: var(--text);
    }
    
    .dark-theme header,
    .dark-theme footer {
      background-color: var(--panel) !important;
      border-color: var(--border) !important;
    }
    
    .dark-theme .card {
      background-color: var(--panel);
      border-color: var(--border);
    }
    
    .dark-theme .text-muted {
      color: var(--muted) !important;
    }
  </style>
</head>
<body>
  <script>
    // Theme Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      
      // Check for saved theme preference or use preferred color scheme
      const savedTheme = localStorage.getItem('theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      
      // Apply the saved theme
      if (savedTheme === 'dark') {
        html.classList.add('dark-theme');
        if (themeToggle) {
          themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
      } else if (themeToggle) {
        themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
      }
      
      // Toggle theme on button click if button exists
      if (themeToggle) {
        themeToggle.addEventListener('click', () => {
          html.classList.toggle('dark-theme');
          const isDark = html.classList.contains('dark-theme');
          localStorage.setItem('theme', isDark ? 'dark' : 'light');
          themeToggle.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
        });
      }
    });
  </script>
