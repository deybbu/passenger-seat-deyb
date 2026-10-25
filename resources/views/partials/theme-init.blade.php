<script>
    (() => {
        const root = document.documentElement;
        let stored = null;
        try {
            stored = localStorage.getItem('ps-app-theme') || localStorage.getItem('ps-admin-theme');
        } catch (error) {
            console.warn('Theme preference could not be read; using system appearance.', error);
        }
        const theme = stored === 'dark' || stored === 'light'
            ? stored
            : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
    })();
</script>
