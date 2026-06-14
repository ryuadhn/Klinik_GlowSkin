// Theme Toggle Helper Logic
(function () {
  // Apply theme immediately to prevent flashing
  const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  if (theme === 'dark') {
    document.documentElement.classList.add('dark');
    document.documentElement.classList.remove('light');
  } else {
    document.documentElement.classList.add('light');
    document.documentElement.classList.remove('dark');
  }
})();

// Function to initialize theme toggle interactions
function setupThemeToggle(buttonId = 'theme-toggle', darkIconId = 'dark-icon', lightIconId = 'light-icon') {
  const themeToggleBtn = document.getElementById(buttonId);
  if (!themeToggleBtn) return;

  const darkIcon = document.getElementById(darkIconId);
  const lightIcon = document.getElementById(lightIconId);
  const html = document.documentElement;

  const updateIcons = () => {
    const isDark = html.classList.contains('dark');
    if (isDark) {
      if (darkIcon) darkIcon.classList.remove('hidden');
      if (lightIcon) lightIcon.classList.add('hidden');
    } else {
      if (darkIcon) darkIcon.classList.add('hidden');
      if (lightIcon) lightIcon.classList.remove('hidden');
    }
  };

  // Run initial state check
  updateIcons();

  themeToggleBtn.addEventListener('click', () => {
    html.classList.toggle('dark');
    html.classList.toggle('light');
    const isDark = html.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateIcons();
  });
}
