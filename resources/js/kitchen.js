document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-kitchen-app]');

    if (!app) return;

    const board = app.querySelector('[data-kitchen-board]');
    const inventory = app.querySelector('[data-kitchen-inventory]');
    const refreshMessage = app.querySelector('[data-refresh-message]');
    const liveStatus = app.querySelector('[data-live-status]');
    const clock = app.querySelector('[data-kitchen-clock]');
    const stockWarning = app.querySelector('[data-stock-warning]');
    const stockWarningText = app.querySelector('[data-stock-warning-text]');
    let activeView = 'orders';
    let activeFilter = 'all';

    const updateClock = () => {
        if (clock) {
            clock.textContent = new Date().toLocaleTimeString('en-PH', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
            });
        }
    };

    const updateTimers = () => {
        app.querySelectorAll('[data-kitchen-timer]').forEach((timer) => {
            const elapsedSeconds = Math.max(0, Math.floor((Date.now() - Number(timer.dataset.startedAt)) / 1000));
            const minutes = Math.floor(elapsedSeconds / 60);
            const seconds = String(elapsedSeconds % 60).padStart(2, '0');
            const value = timer.querySelector('[data-timer-value]');
            const label = timer.querySelector('[data-timer-label]');
            const card = timer.closest('.oc');

            if (!value || !label || !card) return;

            value.textContent = `${String(minutes).padStart(2, '0')}:${seconds}`;

            const isReady = timer.dataset.orderStatus === 'ready';
            const isSlow = !isReady && elapsedSeconds >= 8 * 60 && elapsedSeconds < 15 * 60;
            const isLate = !isReady && elapsedSeconds >= 15 * 60;

            timer.classList.toggle('y', isSlow);
            timer.classList.toggle('r', isLate);
            timer.classList.toggle('n', isReady);
            card.classList.toggle('y', isSlow);
            card.classList.toggle('r', isLate);
            card.classList.toggle('n', isReady);

            if (isReady) {
                label.textContent = 'Waiting for service';
                return;
            }

            label.textContent = isLate ? 'Late' : isSlow ? 'Slow' : 'On time';
        });
    };

    const applyFilter = () => {
        const orderCounts = {};

        app.querySelectorAll('[data-order-column]').forEach((column) => {
            const status = column.dataset.orderColumn;
            const count = column.querySelector('[data-column-count]')?.textContent ?? '0';

            orderCounts[status] = Number(count);
            column.hidden = activeFilter !== 'all' && column.dataset.orderColumn !== activeFilter;
        });

        app.querySelectorAll('[data-order-filter]').forEach((button) => {
            const selected = button.dataset.orderFilter === activeFilter;
            const count = button.dataset.orderFilter === 'all'
                ? Object.values(orderCounts).reduce((sum, value) => sum + value, 0)
                : orderCounts[button.dataset.orderFilter] ?? 0;

            button.classList.toggle('on', selected);
            button.setAttribute('aria-pressed', String(selected));
            const countLabel = button.querySelector('[data-filter-count]');

            if (countLabel) countLabel.textContent = button.dataset.orderFilter === 'all' ? '' : `(${count})`;
        });
    };

    const setView = (view) => {
        activeView = view;

        app.querySelectorAll('[data-kitchen-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.kitchenPanel !== activeView;
        });

        app.querySelectorAll('[data-v]').forEach((button) => {
            const selected = button.dataset.v === activeView;

            if (button.matches('[role="tab"]')) {
                button.classList.toggle('on', selected);
                button.setAttribute('aria-selected', String(selected));
            }
        });
    };

    const setRefreshError = (message) => {
        if (!refreshMessage) return;

        refreshMessage.hidden = !message;
        refreshMessage.textContent = message;

        if (message && liveStatus) liveStatus.textContent = message;
    };

    const fetchPartial = async (url, target, panelName) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            throw new Error(`Kitchen ${panelName} refresh failed with HTTP ${response.status}.`);
        }

        target.innerHTML = await response.text();
    };

    const updateStockWarning = () => {
        const lowStock = inventory.querySelector('[data-low-stock-total]');
        const warningText = inventory.querySelector('[data-stock-warning-text-value]');
        const count = Number(lowStock?.dataset.lowStockTotal ?? 0);

        app.querySelector('[data-low-stock-count]').textContent = String(count);

        if (stockWarning) stockWarning.hidden = count === 0;
        if (stockWarningText && warningText) {
            stockWarningText.textContent = warningText.dataset.stockWarningTextValue;
        }
    };

    const refresh = async () => {
        try {
            await fetchPartial(app.dataset.boardUrl, board, 'orders');
            await fetchPartial(app.dataset.inventoryUrl, inventory, 'inventory');
            const pendingCount = board.querySelector('[data-order-column="pending"] [data-column-count]');

            app.querySelector('[data-pending-count]').textContent = pendingCount?.textContent ?? '0';

            applyFilter();
            updateTimers();
            updateStockWarning();
            setRefreshError('');
            if (liveStatus) liveStatus.textContent = `Kitchen board updated at ${new Date().toLocaleTimeString()}.`;
        } catch (error) {
            console.error(error);
            setRefreshError('Live refresh failed. The screen will try again automatically.');
        }
    };

    app.addEventListener('click', (event) => {
        const button = event.target.closest('button');

        if (!button) return;

        if (button.hasAttribute('data-v')) {
            setView(button.dataset.v);
        } else if (button.hasAttribute('data-order-filter')) {
            activeFilter = button.dataset.orderFilter;
            applyFilter();
        } else if (button.hasAttribute('data-kitchen-theme')) {
            const root = document.documentElement;
            const currentTheme = root.dataset.theme
                ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            root.dataset.theme = nextTheme;
            window.localStorage.setItem('passenger-seat-theme', nextTheme);
            button.setAttribute('aria-label', `Switch to ${nextTheme === 'dark' ? 'light' : 'dark'} mode`);
        }
    });

    const savedTheme = window.localStorage.getItem('passenger-seat-theme');

    if (savedTheme === 'light' || savedTheme === 'dark') {
        document.documentElement.dataset.theme = savedTheme;
    }

    updateClock();
    updateTimers();
    applyFilter();
    updateStockWarning();
    const toast = app.querySelector('[data-message]');

    if (toast?.dataset.message) {
        toast.textContent = toast.dataset.message;
        toast.classList.add('on');
        window.setTimeout(() => toast.classList.remove('on'), 2600);
    }

    refresh();
    window.setInterval(updateClock, 1000);
    window.setInterval(updateTimers, 1000);
    window.setInterval(refresh, 15000);
});
