(() => {
    const themeButton = document.getElementById('theme');
    const navigation = document.getElementById('nav');
    const menuButton = document.getElementById('burger');
    const links = document.getElementById('links');

    if (!themeButton || !navigation || !menuButton || !links) return;

    const setTheme = (theme, persist = true) => {
        document.documentElement.dataset.theme = theme;
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        const label = `Switch to ${nextTheme} mode`;
        themeButton.setAttribute('aria-checked', String(theme === 'dark'));
        themeButton.setAttribute('aria-label', label);
        themeButton.setAttribute('title', label);

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
        navigation.classList.toggle('sc', navigation.dataset.solidNav === 'true' || window.scrollY > 40);
    };

    window.addEventListener('scroll', updateNavigation, { passive: true });
    updateNavigation();

    const rail = document.getElementById('rail');
    const categoryFilters = document.getElementById('rc');
    const categoryProgress = document.getElementById('rcProgress');
    const progress = document.getElementById('pb');
    const previousButton = document.getElementById('rp');
    const nextButton = document.getElementById('rn');
    const toast = document.getElementById('toast');

    if (!rail || !categoryFilters || !categoryProgress || !progress || !previousButton || !nextButton || !toast) return;

    const menuCards = [...rail.querySelectorAll('[data-menu-card]')];
    let toastTimeout;
    let pointerStart = 0;
    let initialScroll = 0;
    let isDragging = false;
    let categoryPointerStart = 0;
    let categoryInitialScroll = 0;
    let isCategoryDragging = false;
    let suppressCategoryClick = false;

    const updateCategoryProgress = () => {
        const maxScroll = categoryFilters.scrollWidth - categoryFilters.clientWidth;
        const ratio = categoryFilters.scrollWidth
            ? categoryFilters.clientWidth / categoryFilters.scrollWidth
            : 1;
        const position = maxScroll > 0 ? categoryFilters.scrollLeft / maxScroll : 0;

        categoryProgress.parentElement.hidden = maxScroll <= 0;
        categoryProgress.style.width = `${ratio * 100}%`;
        categoryProgress.style.transform = `translateX(${(1 - ratio) * position * 100}%)`;
    };

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
        if (suppressCategoryClick) {
            event.preventDefault();
            suppressCategoryClick = false;
            return;
        }

        const button = event.target.closest('button[data-c]');

        if (!button) return;

        categoryFilters.querySelectorAll('button').forEach((filter) => {
            filter.setAttribute('aria-pressed', String(filter === button));
        });
        categoryFilters.addEventListener('scroll', updateCategoryProgress, { passive: true });
        filterMenu(button.dataset.c);
    });

    categoryFilters.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;

        suppressCategoryClick = false;
        categoryPointerStart = event.clientX;
        categoryInitialScroll = categoryFilters.scrollLeft;
        isCategoryDragging = true;
    });

    window.addEventListener('pointermove', (event) => {
        if (!isCategoryDragging) return;

        const distance = event.clientX - categoryPointerStart;

        if (Math.abs(distance) > 5) {
            categoryFilters.classList.add('drag');
            suppressCategoryClick = true;
            categoryFilters.scrollLeft = categoryInitialScroll - distance;
        }
    });

    window.addEventListener('pointerup', () => {
        if (!isCategoryDragging) return;

        isCategoryDragging = false;
        categoryFilters.classList.remove('drag');
    });
    window.addEventListener('resize', updateCategoryProgress);

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

    updateRailControls();
    updateCategoryProgress();
})();
