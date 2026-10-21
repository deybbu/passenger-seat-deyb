(() => {
    const view = document.querySelector('#view');
    if (!view) return;

    let tables = [];
    let refreshError = '';

    const page = () => window.location.pathname.replace(/\/+$/, '') || '/admin';
    const statusClass = (status) => ({
        bill_requested: 'wn',
        open: 'in',
        free: 'gr',
    }[status] || 'gr');
    const statusText = (table) => {
        const label = table.table_status_label;
        const orderCount = table.table_order_count;
        const qrNote = table.qr_enabled ? '' : ' · QR disabled';

        return `${label} · ${orderCount} orders${qrNote}`;
    };

    const renderDashboard = () => {
        let panel = view.querySelector('[data-table-status-panel]');
        const signature = JSON.stringify([tables, refreshError]);
        if (panel?.dataset.signature === signature) return;

        if (!panel) {
            panel = document.createElement('section');
            panel.className = 'card mt';
            panel.dataset.tableStatusPanel = '';
            panel.setAttribute('aria-labelledby', 'table-status-heading');
            view.append(panel);
        }

        panel.replaceChildren();
        panel.dataset.signature = signature;

        const heading = document.createElement('h3');
        heading.id = 'table-status-heading';
        heading.textContent = 'Table status';
        panel.append(heading);

        if (refreshError) {
            const message = document.createElement('p');
            message.className = 'mu';
            message.setAttribute('role', 'status');
            message.textContent = refreshError;
            panel.append(message);
            return;
        }

        if (tables.length === 0) {
            const message = document.createElement('p');
            message.className = 'mu';
            message.textContent = 'No restaurant tables configured.';
            panel.append(message);
            return;
        }

        const rows = document.createElement('div');
        rows.className = 'g';
        tables.forEach((table) => {
            const row = document.createElement('div');
            row.className = 'row';
            row.style.justifyContent = 'space-between';
            row.style.padding = '6px 0';

            const name = document.createElement('strong');
            name.textContent = `Table ${table.table_number}`;
            const status = document.createElement('span');
            status.className = `b ${statusClass(table.table_status)}`;
            status.textContent = refreshError ? 'Status unavailable' : statusText(table);
            row.append(name, status);
            rows.append(row);
        });
        panel.append(rows);
    };

    const renderTableQr = () => {
        view.querySelectorAll('[data-a="tableToggle"]').forEach((button) => {
            const table = tables.find((entry) => String(entry.id) === button.dataset.v);
            const card = button.closest('.card');
            if (!table || !card) return;

            let status = card.querySelector('[data-table-session-status]');
            if (!status) {
                status = document.createElement('div');
                status.className = 'mu';
                status.dataset.tableSessionStatus = '';
                status.setAttribute('role', 'status');
                card.querySelector('.mu')?.insertAdjacentElement('afterend', status);
            }
            const text = refreshError ? 'Table status unavailable' : statusText(table);
            if (status.textContent !== text) status.textContent = text;
        });
    };

    const renderRefreshError = () => {
        let message = view.querySelector('[data-table-status-error]');
        if (!refreshError) {
            message?.remove();
            return;
        }
        if (!message) {
            message = document.createElement('div');
            message.className = 'msg wn';
            message.dataset.tableStatusError = '';
            message.setAttribute('role', 'status');
            view.prepend(message);
        }
        if (message.textContent !== refreshError) message.textContent = refreshError;
    };

    const render = () => {
        renderRefreshError();
        if (page() === '/admin' || page() === '/admin/dashboard') renderDashboard();
        if (page() === '/admin/table-qr') renderTableQr();
    };

    const refresh = async () => {
        try {
            const response = await fetch('/admin/api/reference/dashboard', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) {
                throw new Error(`Table status refresh failed with HTTP ${response.status}.`);
            }

            const data = await response.json();
            const validStatuses = ['free', 'open', 'bill_requested'];
            if (!Array.isArray(data.tables) || data.tables.some((table) => (
                !table
                || !Number.isInteger(Number(table.id))
                || typeof table.table_number !== 'string'
                || !validStatuses.includes(table.table_status)
                || typeof table.table_status_label !== 'string'
                || !Number.isInteger(table.table_order_count)
                || typeof table.qr_enabled !== 'boolean'
            ))) {
                throw new Error('Table status response has an invalid shape.');
            }
            tables = data.tables;
            refreshError = '';
        } catch (error) {
            console.error(error);
            refreshError = 'Table status is temporarily unavailable. Retrying automatically.';
        }
        render();
    };

    new MutationObserver(render).observe(view, { childList: true, subtree: true });
    window.addEventListener('popstate', render);
    refresh();
    window.setInterval(refresh, 15000);
})();
