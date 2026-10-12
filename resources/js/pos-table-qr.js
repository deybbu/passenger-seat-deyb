document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('#qr-order-root');
    if (!root) return;

    const items = JSON.parse(root.dataset.items || '[]');
    const tables = JSON.parse(root.dataset.tables || '[]');
    const cart = new Map();
    const tableSelect = document.querySelector('#qTbl');
    const menu = document.querySelector('#qMenu');
    const lines = document.querySelector('#qLines');
    const total = document.querySelector('#qTot');
    const send = document.querySelector('#qSend');
    const toast = document.querySelector('#toast');
    let sending = false;
    let toastTimer;

    const money = (value) => `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('on'), 3000);
    };
    const newSubmissionToken = () => {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };
    const itemById = (id) => items.find((item) => item.id === Number(id));
    const availableCount = (item) => item.stock === null ? null : Math.max(0, item.stock);

    tables.forEach((table) => {
        const option = document.createElement('option');
        option.value = table.number;
        option.textContent = table.number;
        option.dataset.token = table.token;
        tableSelect.append(option);
    });
    const requestedTable = new URLSearchParams(window.location.search).get('table');
    if (tables.some((table) => table.number === requestedTable)) tableSelect.value = requestedTable;

    const render = () => {
        menu.innerHTML = items.map((item) => {
            const remaining = availableCount(item);
            const soldOut = !item.available || (remaining !== null && remaining <= (cart.get(item.id) || 0));
            const stockLabel = !item.available
                ? 'Sold out'
                : remaining === null ? 'Available' : `${Math.max(0, remaining - (cart.get(item.id) || 0))} left`;
            const photo = item.photo
                ? `<img class="thumb" alt="" src="${escapeHtml(item.photo)}">`
                : '<span class="thumb" aria-hidden="true"></span>';

            return `<button class="item" type="button" data-item="${item.id}"${soldOut ? ' disabled' : ''}>${photo}<b>${escapeHtml(item.name)}</b><small>${escapeHtml(item.category)} · ${stockLabel}</small><div class="p">${money(item.price)}</div></button>`;
        }).join('') || '<p class="mute">No menu items are available.</p>';

        const cartItems = [...cart.entries()];
        lines.innerHTML = cartItems.length
            ? cartItems.map(([id, quantity]) => {
                const item = itemById(id);
                return `<div class="line"><div><b>${escapeHtml(item.name)}</b><div class="mute">${money(item.price)}</div></div><span class="q"><button type="button" data-change="-1" data-id="${id}" aria-label="Remove one">−</button><b>${quantity}</b><button type="button" data-change="1" data-id="${id}" aria-label="Add one">+</button></span><b>${money(item.price * quantity)}</b></div>`;
            }).join('')
            : '<p class="mute">Tap an item to add it.</p>';

        const amount = cartItems.reduce((sum, [id, quantity]) => sum + itemById(id).price * quantity, 0);
        total.textContent = money(amount);
        send.disabled = sending || cartItems.length === 0 || tableSelect.value === '';
    };

    menu.addEventListener('click', (event) => {
        const button = event.target.closest('[data-item]');
        if (!button) return;
        const item = itemById(button.dataset.item);
        if (!item || !item.available) return;
        const quantity = (cart.get(item.id) || 0) + 1;
        const remaining = availableCount(item);
        if (quantity > 20 || (remaining !== null && quantity > remaining)) {
            showToast(`Only ${remaining ?? 20} ${item.name} available.`);
            return;
        }
        cart.set(item.id, quantity);
        render();
    });

    lines.addEventListener('click', (event) => {
        const button = event.target.closest('[data-change]');
        if (!button) return;
        const id = Number(button.dataset.id);
        const item = itemById(id);
        const nextQuantity = (cart.get(id) || 0) + Number(button.dataset.change);
        const remaining = item ? availableCount(item) : 0;

        if (nextQuantity <= 0) {
            cart.delete(id);
        } else if (item && item.available && (remaining === null || nextQuantity <= remaining)) {
            if (nextQuantity <= 20) cart.set(id, nextQuantity);
            else showToast(`You can order up to 20 ${item.name}.`);
        } else if (item) {
            showToast(`Only ${remaining} ${item.name} available.`);
        }

        render();
    });

    tableSelect.addEventListener('change', render);

    send.addEventListener('click', async () => {
        const selectedTable = tableSelect.selectedOptions[0];
        if (!selectedTable?.dataset.token || sending || cart.size === 0) return;

        sending = true;
        send.textContent = 'Sending order…';
        render();

        try {
            const response = await fetch(root.dataset.placeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    table_token: selectedTable.dataset.token,
                    submission_token: root.dataset.submissionToken,
                    items: Object.fromEntries(cart),
                }),
            });
            const result = await response.json();

            if (!response.ok) {
                const validationMessage = Object.values(result.errors || {}).flat()[0];
                throw new Error(validationMessage || result.message || 'The order could not be sent.');
            }

            showToast(`Order ${result.reference} sent to the kitchen.`);
            cart.clear();
            root.dataset.submissionToken = newSubmissionToken();
        } catch (error) {
            showToast(error instanceof Error ? error.message : 'The order could not be sent.');
        } finally {
            sending = false;
            send.textContent = 'Send order to kitchen';
            render();
        }
    });

    render();
});
