document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('#tabs')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-url]');
        if (button) window.location.assign(button.dataset.url);
    });

    const themeButton = document.querySelector('#theme');
    const savedTheme = window.localStorage.getItem('passenger-seat-theme');

    if (savedTheme === 'light' || savedTheme === 'dark') {
        document.documentElement.dataset.theme = savedTheme;
    } else {
        document.documentElement.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    const updateThemeButton = () => {
        const isDark = document.documentElement.dataset.theme === 'dark';
        themeButton.textContent = isDark ? 'Light mode' : 'Dark mode';
        themeButton.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
    };

    themeButton?.addEventListener('click', () => {
        document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark'
            ? 'light'
            : 'dark';
        window.localStorage.setItem('passenger-seat-theme', document.documentElement.dataset.theme);
        updateThemeButton();
    });
    updateThemeButton();

    const clock = document.querySelector('#clock');
    const updateClock = () => {
        clock.textContent = new Date().toLocaleString();
    };
    updateClock();
    window.setInterval(updateClock, 1000);

    const toast = document.querySelector('#toast');
    if (toast?.dataset.message) {
        toast.textContent = toast.dataset.message;
        toast.classList.add('on');
        window.setTimeout(() => toast.classList.remove('on'), 3000);
    }

    if (document.querySelector('[data-pos-orders]')) {
        window.setInterval(() => window.location.reload(), 15000);
    }
});
