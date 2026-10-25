import './bootstrap';

if (document.querySelector('[data-react-root]')) {
    import('./react-islands.jsx');
}

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('[data-theme-toggle]');

    if (themeToggle) {
        const updateThemeControl = () => {
            const isDark = document.documentElement.dataset.theme === 'dark';

            themeToggle.setAttribute('aria-pressed', String(isDark));
            themeToggle.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
            themeToggle.setAttribute('aria-checked', String(isDark));
        };

        updateThemeControl();

        themeToggle.addEventListener('click', () => {
            const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';

            document.documentElement.dataset.theme = theme;
            window.localStorage.setItem('passenger-seat-theme', theme);
            updateThemeControl();
        });
    }

    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('#primary-nav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
});
