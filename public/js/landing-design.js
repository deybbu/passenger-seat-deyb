(() => {
    const themeButton = document.getElementById('theme');
    const navigation = document.getElementById('nav');
    const menuButton = document.getElementById('burger');
    const links = document.getElementById('links');

    if (!themeButton || !navigation || !menuButton || !links) return;

    const setTheme = (theme, persist = true) => {
        document.documentElement.dataset.theme = theme;
        themeButton.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        themeButton.setAttribute('aria-pressed', String(theme === 'dark'));

        if (persist) {
            try {
                window.localStorage.setItem('ps_site2', theme);
            } catch {
                // The selected theme still applies for this page load.
            }
        }
    };

    let savedTheme = null;

    try {
        savedTheme = window.localStorage.getItem('ps_site2');
    } catch {
        // Use the system preference when browser storage is unavailable.
    }

    setTheme(
        savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
        false,
    );

    themeButton.addEventListener('click', () => {
        setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    });

    menuButton.addEventListener('click', () => {
        const isOpen = links.classList.toggle('open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });

    links.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            links.classList.remove('open');
            menuButton.setAttribute('aria-expanded', 'false');
        }
    });

    const updateNavigation = () => {
        navigation.classList.toggle('sc', window.scrollY > 40);
    };

    window.addEventListener('scroll', updateNavigation, { passive: true });
    updateNavigation();

    const rail = document.getElementById('rail');
    const categoryFilters = document.getElementById('rc');
    const progress = document.getElementById('pb');
    const previousButton = document.getElementById('rp');
    const nextButton = document.getElementById('rn');
    const toast = document.getElementById('toast');

    if (!rail || !categoryFilters || !progress || !previousButton || !nextButton || !toast) return;

    const menuCards = [...rail.querySelectorAll('[data-menu-card]')];
    let toastTimeout;
    let pointerStart = 0;
    let initialScroll = 0;
    let isDragging = false;

    const updateRailControls = () => {
        const maxScroll = rail.scrollWidth - rail.clientWidth;
        const ratio = rail.scrollWidth ? rail.clientWidth / rail.scrollWidth : 1;
        const position = maxScroll > 0 ? rail.scrollLeft / maxScroll : 0;

        progress.style.width = `${ratio * 100}%`;
        progress.style.marginLeft = `${(1 - ratio) * position * 100}%`;
        previousButton.disabled = position <= 0.01;
        nextButton.disabled = position >= 0.99;
    };

    const filterMenu = (category) => {
        menuCards.forEach((card) => {
            const matches = category === 'all' || card.dataset.category === category;
            card.dataset.filtered = String(!matches);
        });

        rail.scrollLeft = 0;
        updateRailControls();
    };

    categoryFilters.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-c]');

        if (!button) return;

        categoryFilters.querySelectorAll('button').forEach((filter) => {
            filter.setAttribute('aria-pressed', String(filter === button));
        });
        filterMenu(button.dataset.c);
    });

    const scrollRail = (direction) => {
        const card = rail.querySelector('[data-menu-card]:not([data-filtered="true"])');

        if (!card) return;

        const styles = window.getComputedStyle(rail);
        const gap = Number.parseFloat(styles.columnGap || styles.gap) || 0;
        const distance = (card.getBoundingClientRect().width + gap) * direction;

        rail.scrollBy({
            left: distance,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    };

    previousButton.addEventListener('click', () => scrollRail(-1));
    nextButton.addEventListener('click', () => scrollRail(1));
    rail.addEventListener('scroll', updateRailControls, { passive: true });
    window.addEventListener('resize', updateRailControls);

    rail.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;

        pointerStart = event.clientX;
        initialScroll = rail.scrollLeft;
        isDragging = true;
        rail.classList.add('drag');
    });

    window.addEventListener('pointermove', (event) => {
        if (!isDragging) return;

        rail.scrollLeft = initialScroll - (event.clientX - pointerStart);
    });

    window.addEventListener('pointerup', () => {
        if (!isDragging) return;

        isDragging = false;
        rail.classList.remove('drag');
    });

    rail.addEventListener('click', (event) => {
        const orderButton = event.target.closest('[data-order-item]');

        if (!orderButton || orderButton.disabled) return;

        toast.textContent = `Scan the QR code at your table to order ${orderButton.dataset.orderItem}.`;
        toast.classList.add('on');
        window.clearTimeout(toastTimeout);
        toastTimeout = window.setTimeout(() => toast.classList.remove('on'), 2600);
    });

    document.querySelectorAll('#vShow [data-order-item], #vCards [data-order-item]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.disabled) return;

            toast.textContent = `Scan the QR code at your table to order ${button.dataset.orderItem}.`;
            toast.classList.add('on');
            window.clearTimeout(toastTimeout);
            toastTimeout = window.setTimeout(() => toast.classList.remove('on'), 2600);
        });
    });

    document.querySelector('.vt')?.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-v]');

        if (!button) return;

        document.querySelectorAll('.vt button').forEach((viewButton) => {
            viewButton.setAttribute('aria-pressed', String(viewButton === button));
        });

        document.getElementById('vScroll').hidden = button.dataset.v !== 'scroll';
        document.getElementById('vShow').hidden = button.dataset.v !== 'show';
        document.getElementById('vCards').hidden = button.dataset.v !== 'cards';

        if (button.dataset.v === 'scroll') updateRailControls();
    });

    updateRailControls();
})();
