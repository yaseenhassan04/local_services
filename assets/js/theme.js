function toggleTheme() {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
    updateIcon(isLight);
}

function updateIcon(isLight) {
    const icon  = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if (icon)  icon.className = isLight ? 'las la-moon' : 'las la-sun';
    if (label) label.textContent = isLight ? 'الوضع الداكن' : 'الوضع المضيء';
}

(function(){
    const saved = localStorage.getItem('theme');
    if (saved === 'light') {
        document.body.classList.add('light-mode');
        document.addEventListener('DOMContentLoaded', () => updateIcon(true));
    }
})();