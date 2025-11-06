    </main>
    <footer style="text-align: center; padding: 2.5rem 2rem; color: #64748B; font-size: 0.8rem; border-top: 1px solid #E2E8F0; margin-top: 3rem; background-color: #FFFFFF;">
      <p style="margin-bottom: 0.25rem;">&copy; 2025 College Feedback Portal. All rights reserved.</p>
      <p>Enhancing education through continuous feedback and improvement.</p>
    </footer>
  </div>
</div>
<script>
  // Theme Toggle Functionality
  const themeToggle = document.getElementById('themeToggle');
  const html = document.documentElement;
  
  // Check for saved theme preference or use preferred color scheme
  const savedTheme = localStorage.getItem('theme') || 
    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  
  // Apply the saved theme
  if (savedTheme === 'dark') {
    html.classList.add('dark-theme');
    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
  } else {
    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
  }
  
  // Toggle theme on button click
  themeToggle.addEventListener('click', () => {
    html.classList.toggle('dark-theme');
    const isDark = html.classList.contains('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    themeToggle.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
  });
  
  // Add dark theme styles
  const style = document.createElement('style');
  style.textContent = `
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
    
    .dark-theme .sidebar,
    .dark-theme header,
    .dark-theme footer {
      background-color: var(--panel);
      border-color: var(--border);
    }
    
    .dark-theme .card {
      background-color: var(--panel);
      border-color: var(--border);
    }
    
    .dark-theme .text-muted {
      color: var(--muted) !important;
    }
  `;
  document.head.appendChild(style);
</script>
</body>
</html>
