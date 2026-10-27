document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('#qr-root');

    if (!root) return;

    const menu = JSON.parse(root.dataset.items);
    const categories = ['All', ...new Set(JSON.parse(root.dataset.categories))];
    const screen = document.querySelector('#scr');
    const overlay = document.querySelector('#overlay');
    const alert = document.querySelector('#qr-alert');
    const toast = document.querySelector('#toast');
    const ordersToggle = document.querySelector('#qr-orders-toggle');
    const orderCount = document.querySelector('#qr-order-count');
    const cart = new Map();
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let selectedCategory = 'All';
    let selectedItemId = menu[0]?.id ?? null;
    let search = '';
    let showingOrders = false;
    let note = '';
    let orders = [];
    let billRequested = false;
    let billSubmitting = false;
    let submitting = false;
    let toastTimer;
    let orderRefresh;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);
    const quantity = () => [...cart.values()].reduce((sum, value) => sum + value, 0);
    const total = () => menu.reduce((sum, item) => sum + item.price * (cart.get(item.id) || 0), 0);
    const itemById = (id) => menu.find((item) => item.id === Number(id));

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('on'), 2200);
    };

    const showAlert = (message) => {
        alert.textContent = message;
        alert.hidden = false;
    };

    const hideAlert = () => {
        alert.textContent = '';
        alert.hidden = true;
    };

    const photoMarkup = (item, classes) => {
        if (item.photo) {
            return `<img class="${classes}" src="${escapeHtml(item.photo)}" alt="" loading="lazy">`;
        }

        return `<div class="${classes} photo-placeholder" aria-hidden="true">${escapeHtml(item.name.charAt(0).toUpperCase())}</div>`;
    };

    const filteredMenu = () => {
        const needle = search.trim().toLocaleLowerCase();

        return menu.filter((item) => {
            const matchesCategory = selectedCategory === 'All' || item.category === selectedCategory;
            const matchesSearch = !needle
                || `${item.name} ${item.description || ''}`.toLocaleLowerCase().includes(needle);

            return matchesCategory && matchesSearch;
        });
    };

    const renderMenu = (resetScroll = false) => {
        const previousScroll = screen.querySelector('.scr')?.scrollTop ?? 0;
        const items = filteredMenu();
        const featured = menu.find((item) => item.id === selectedItemId) || items[0];
        const tabs = categories.map((category) => `
            <button type="button" role="tab" data-p="cat" data-c="${escapeHtml(category)}"
                aria-selected="${selectedCategory === category}">${escapeHtml(category)}</button>
        `).join('');
        const featuredMarkup = featured ? (() => {
            const selectedQuantity = cart.get(featured.id) || 0;
            const ingredients = featured.ingredients.length
                ? featured.ingredients.map((ingredient) => `<span>${escapeHtml(ingredient)}</span>`).join('')
                : '<span>Not listed</span>';
            const action = selectedQuantity
                ? `<span class="q">
                    <button type="button" data-p="dec" data-i="${featured.id}" aria-label="Remove one ${escapeHtml(featured.name)}">−</button>
                    <b>${selectedQuantity}</b>
                    <button class="p" type="button" data-p="inc" data-i="${featured.id}" aria-label="Add one ${escapeHtml(featured.name)}"${selectedQuantity >= 20 ? ' disabled' : ''}>+</button>
                </span>`
                : `<button class="btn red pill" type="button" data-p="inc" data-i="${featured.id}">Add to order</button>`;

            return `
                <div class="feat">
                    ${photoMarkup(featured, 'fi')}
                    <div class="fn">${escapeHtml(featured.name)}</div>
                    ${featured.description ? `<div class="description"><small>Description</small><p class="fd">${escapeHtml(featured.description)}</p></div>` : ''}
                    <div class="fpr">${money.format(featured.price)}</div>
                    <div class="ing"><small>Ingredients</small><div class="ic">${ingredients}</div></div>
                    ${action}
                </div>
            `;
        })() : '<div class="center"><p class="mute">No dishes are available for this table right now.</p></div>';
        const tiles = items.map((item) => {
            const inCart = cart.get(item.id) || 0;

            return `
                <div class="tile${item.id === featured?.id ? ' sel' : ''}">
                    <button class="tp" type="button" data-p="sel" data-i="${item.id}" aria-label="View ${escapeHtml(item.name)}">
                        ${photoMarkup(item, '')}
                        <b>${escapeHtml(item.name)}</b>
                    </button>
                    <div class="tr">
                        <span>${money.format(item.price)}${inCart ? ` · ×${inCart}` : ''}</span>
                        <button class="plus" type="button" data-p="inc" data-i="${item.id}" aria-label="Add ${escapeHtml(item.name)}"${inCart >= 20 ? ' disabled' : ''}>+</button>
                    </div>
                </div>
            `;
        }).join('') || '<p class="mute">No dishes match.</p>';

        screen.innerHTML = `
            <div class="scr">
                <div class="tabs" role="tablist" aria-label="Menu categories">${tabs}</div>
                ${featuredMarkup}
                <label class="sr-only" for="cq">Search the menu</label>
                <input id="cq" type="search" placeholder="Search the menu" aria-label="Search the menu" value="${escapeHtml(search)}">
                <div class="tiles">${tiles}</div>
            </div>
        `;

        if (quantity() > 0) {
            const cartButton = document.createElement('button');
            cartButton.className = 'cbar';
            cartButton.type = 'button';
            cartButton.dataset.p = 'cart';
            cartButton.innerHTML = `<span>View order · ${quantity()} item${quantity() === 1 ? '' : 's'}</span><span>${money.format(total())}</span>`;
            screen.appendChild(cartButton);
        }

        screen.querySelectorAll('[data-p="cat"]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.c === selectedCategory));
        });
        screen.querySelectorAll('.tabs [role="tab"]').forEach((button) => {
            button.setAttribute('aria-selected', String(button.dataset.c === selectedCategory));
        });

        const scrollable = screen.querySelector('.scr');
        if (scrollable) scrollable.scrollTop = resetScroll ? 0 : previousScroll;
    };

    const unpaidOrders = () => orders.filter((order) => order.payment_status !== 'paid'
        && order.status !== 'cancelled');

    const renderOrders = () => {
        const statusSteps = ['pending', 'preparing', 'ready', 'served'];
        const statuses = {
            pending: 'Received',
            preparing: 'Preparing',
            ready: 'Ready',
            served: 'Served',
        };
        const orderCards = orders.length ? orders.map((order) => {
            const currentStep = statusSteps.indexOf(order.status);
            const progress = order.status === 'cancelled'
                ? '<div class="status-cancelled">Order cancelled</div>'
                : `<div class="steps" aria-label="Order status: ${escapeHtml(statuses[order.status] || order.status)}">
                    ${statusSteps.map((status, index) => `
                        <div class="${index <= currentStep ? 'on' : ''}"><i></i>${index <= currentStep ? '✓ ' : ''}${statuses[status]}</div>
                    `).join('')}
                </div>`;
            const orderItems = order.items
                .map((item) => `${item.quantity}× ${escapeHtml(item.name)}`)
                .join(', ');

            return `
                <article class="oc">
                    <b>Order #${escapeHtml(order.reference)}</b>
                    <span class="mute">${orderItems}</span>
                    ${order.special_instructions ? `<div class="mute">Note: ${escapeHtml(order.special_instructions)}</div>` : ''}
                    ${progress}
                    <div style="text-align:right;font-weight:700">${money.format(order.total)}</div>
                </article>
            `;
        }).join('') : `
            <div class="center">
                <p class="mute">No orders yet.</p>
                <button class="btn red" type="button" data-p="menu">Browse the menu</button>
            </div>
        `;
        const unpaidTotal = unpaidOrders().reduce((sum, order) => sum + order.total, 0);
        const billContent = billRequested
            ? '<div class="oc" style="text-align:center"><b>✓ Bill requested.</b> Our staff is on the way.</div>'
            : unpaidTotal > 0
                ? `<button class="btn red w" type="button" data-p="bill"${billSubmitting ? ' disabled' : ''}>${billSubmitting ? 'Requesting the bill…' : 'Request the bill'}</button>`
                : '<div class="bill-note">There are no outstanding orders on this table.</div>';

        screen.innerHTML = `
            <div class="scr">
                <h2 style="margin-top:12px">My orders, Table ${escapeHtml(root.dataset.table)}</h2>
                ${orderCards}
                ${orders.length ? `
                    <div class="my-total"><span>Your bill</span><span>${money.format(unpaidTotal)}</span></div>
                    ${billContent}
                    <p><button class="btn w" type="button" data-p="menu">Order more</button></p>
                ` : ''}
            </div>
        `;
    };

    const refreshOrders = async () => {
        if (orderRefresh) return orderRefresh;

        orderRefresh = (async () => {
            try {
                const response = await fetch(root.dataset.ordersUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) throw new Error(`Could not refresh orders (HTTP ${response.status}).`);

                const data = await response.json();
                orders = data.orders;
                billRequested = data.bill_requested;
                orderCount.textContent = String(orders.length);
                orderCount.hidden = orders.length === 0;
                hideAlert();

                if (showingOrders) renderOrders();
            } catch (error) {
                console.error(error);
                showAlert('Could not refresh your orders. Check your connection and try again.');
            } finally {
                orderRefresh = null;
            }
        })();

        return orderRefresh;
    };

    const renderCart = () => {
        const lines = [...cart.entries()].map(([id, itemQuantity]) => {
            const item = itemById(id);
            if (!item) return '';

            return `
                <div class="ln">
                    <span>${escapeHtml(item.name)}<br><small class="mute">${money.format(item.price)}</small></span>
                    <span class="q">
                        <button type="button" data-p="dec" data-i="${item.id}" aria-label="Remove one ${escapeHtml(item.name)}">−</button>
                        <b>${itemQuantity}</b>
                        <button class="p" type="button" data-p="inc" data-i="${item.id}" aria-label="Add one ${escapeHtml(item.name)}"${itemQuantity >= 20 ? ' disabled' : ''}>+</button>
                    </span>
                </div>
            `;
        }).join('');

        overlay.innerHTML = `
            <div class="ov" data-p="x">
                <section class="sheet" role="dialog" aria-modal="true" aria-labelledby="cart-heading">
                    <h2 id="cart-heading">Your order · Table ${escapeHtml(root.dataset.table)}</h2>
                    ${lines}
                    <label class="sr-only" for="cn">Kitchen notes for your order</label>
                    <input id="cn" maxlength="500" aria-label="Kitchen notes for your order" placeholder="Kitchen notes or special requests (optional)" value="${escapeHtml(note)}">
                    <div class="tot"><span>Total</span><span>${money.format(total())}</span></div>
                    <button class="btn red w" type="button" data-p="send"${submitting || quantity() === 0 ? ' disabled' : ''}>${submitting ? 'Sending…' : 'Send to kitchen'}</button>
                    <p><button class="btn w" type="button" data-p="close">Keep browsing</button></p>
                </section>
            </div>
        `;
    };

    const newSubmissionToken = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const placeOrder = async (button) => {
        if (submitting || quantity() === 0) return;

        submitting = true;
        button.disabled = true;
        button.textContent = 'Sending…';
        hideAlert();

        try {
            const response = await fetch(root.dataset.placeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    table_token: root.dataset.tableToken,
                    submission_token: root.dataset.submissionToken,
                    items: Object.fromEntries(cart),
                    special_instructions: note,
                }),
            });
            const result = await response.json();

            if (!response.ok) throw new Error(result.message || 'Your order could not be submitted.');

            cart.clear();
            note = '';
            root.dataset.submissionToken = newSubmissionToken();
            overlay.innerHTML = '';
            showingOrders = true;
            ordersToggle.setAttribute('aria-pressed', 'true');
            hideAlert();
            showToast(`Order ${result.reference} sent to the kitchen.`);
            await refreshOrders();
        } catch (error) {
            console.error(error);
            submitting = false;
            showAlert(error.message || 'Your order could not be submitted. Please try again.');
            renderCart();
        } finally {
            submitting = false;
        }
    };

    const requestBill = async (button) => {
        if (billSubmitting || billRequested) return;

        billSubmitting = true;
        button.disabled = true;
        button.textContent = 'Requesting the bill…';
        hideAlert();

        try {
            const response = await fetch(root.dataset.billUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const result = await response.json();

            if (!response.ok) throw new Error(result.message || 'Could not request the bill.');

            billRequested = true;
            showToast(result.message);
            await refreshOrders();
        } catch (error) {
            console.error(error);
            showAlert(error.message || 'Could not request the bill. Please try again.');
        } finally {
            billSubmitting = false;
            if (showingOrders) renderOrders();
        }
    };

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-p]');
        if (!button) return;

        const action = button.dataset.p;

        if (action === 'cat') {
            selectedCategory = button.dataset.c;
            const firstMatch = filteredMenu()[0];
            if (firstMatch) selectedItemId = firstMatch.id;
            renderMenu(true);
        } else if (action === 'sel') {
            selectedItemId = Number(button.dataset.i);
            renderMenu();
        } else if (action === 'inc' || action === 'dec') {
            const id = Number(button.dataset.i);
            const nextQuantity = (cart.get(id) || 0) + (action === 'inc' ? 1 : -1);

            if (nextQuantity <= 0) cart.delete(id);
            else if (nextQuantity <= 20) cart.set(id, nextQuantity);
            else return;

            if (overlay.querySelector('.ov')) {
                if (quantity() === 0) overlay.innerHTML = '';
                else renderCart();
            } else {
                renderMenu();
            }
        } else if (action === 'cart') {
            renderCart();
        } else if (action === 'x') {
            if (event.target === button) overlay.innerHTML = '';
        } else if (action === 'close') {
            overlay.innerHTML = '';
        } else if (action === 'send') {
            await placeOrder(button);
        } else if (action === 'bill') {
            await requestBill(button);
        } else if (action === 'menu') {
            showingOrders = false;
            ordersToggle.setAttribute('aria-pressed', 'false');
            overlay.innerHTML = '';
            renderMenu(true);
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.id === 'cn') {
            note = event.target.value;
        } else if (event.target.id === 'cq') {
            search = event.target.value;
            const cursor = event.target.selectionStart;
            renderMenu();
            const input = screen.querySelector('#cq');
            input.focus();
            input.setSelectionRange(cursor, cursor);
        }
    });

    ordersToggle.addEventListener('click', async () => {
        showingOrders = !showingOrders;
        ordersToggle.setAttribute('aria-pressed', String(showingOrders));
        overlay.innerHTML = '';

        if (showingOrders) await refreshOrders();
        else renderMenu(true);
    });

    renderMenu();
    window.setInterval(() => {
        if (showingOrders) refreshOrders();
    }, 5000);
});
