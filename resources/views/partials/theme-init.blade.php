<script>
    (() => {
        try {
            const stored = localStorage.getItem('ps-app-theme') || localStorage.getItem('ps-admin-theme');
            const theme = stored === 'dark' || stored === 'light'
                ? stored
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
        } catch {
            document.documentElement.dataset.theme = 'light';
        }
    })();
</script>
