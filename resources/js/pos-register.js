document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#register-form');

    if (!form) return;

    const menu = JSON.parse(form.dataset.menuItems || '[]');
    const categories = ['All', ...JSON.parse(form.dataset.categories || '[]')];
    const cart = new Map();
    const held = [];
    Object.entries(JSON.parse(form.dataset.oldItems || '{}')).forEach(([id, quantity]) => {
        const item = menu.find((candidate) => candidate.id === Number(id));
        const count = Number(quantity);
        if (item && Number.isInteger(count) && count > 0) cart.set(item.id, Math.min(20, count));
    });
    let category = 'All';
    let orderType = form.dataset.oldType === 'take_out' ? 'Take-out' : 'Dine-in';
    let paymentMethod = ({ cash: 'Cash', gcash: 'GCash', card: 'Card' })[form.dataset.oldPaymentMethod] || 'Cash';
    let toastTimer;
    const money = (value) => `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const cents = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
    const itemById = (id) => menu.find((item) => item.id === Number(id));
    const toast = (message) => {
        const element = document.querySelector('#toast');
        element.textContent = message;
        element.classList.add('on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => element.classList.remove('on'), 2200);
    };

    const totals = () => {
        const subtotal = cents([...cart].reduce((sum, [id, quantity]) => {
            const item = itemById(id);
            return sum + (item ? item.price * quantity : 0);
        }, 0));
        const type = document.querySelector('#dtype').value;

        if (type === 'SC' || type === 'PWD') {
            const vatExclusive = cents(subtotal / 1.12);
            const discount = cents(vatExclusive * 0.2);
            return { subtotal, exemption: cents(subtotal - vatExclusive), discount, total: cents(vatExclusive - discount), vat: 0 };
        }

        const rate = type === 'CUSTOM'
            ? Math.min(100, Math.max(0, Number(document.querySelector('#disc').value) || 0))
            : 0;
        const discount = cents(subtotal * rate / 100);
        const total = cents(subtotal - discount);

        return { subtotal, exemption: 0, discount, total, vat: cents(total * 12 / 112) };
    };

    const idDiscountIsValid = () => {
        const type = document.querySelector('#dtype').value;
        return !['SC', 'PWD'].includes(type)
            || Boolean(document.querySelector('#idName').value.trim() && document.querySelector('#idNo').value.trim());
    };

    const renderMenu = () => {
        const query = document.querySelector('#q').value.trim().toLocaleLowerCase();
        const categoryButtons = categories.map((name) => `
            <button class="btn sm${name === category ? ' on' : ''}" type="button"
                data-category="${escapeHtml(name)}">${escapeHtml(name)}</button>
        `).join('');
        document.querySelector('#cats').innerHTML = categoryButtons;

        const filtered = menu.filter((item) => (
            (category === 'All' || item.category === category)
            && `${item.name} ${item.category}`.toLocaleLowerCase().includes(query)
        ));
        document.querySelector('#menu').innerHTML = filtered.length
            ? filtered.map((item) => {
                const quantity = cart.get(item.id) || 0;
                const left = item.stock === null ? null : Math.max(0, item.stock - quantity);
                const unavailable = !item.available || left === 0;
                const stockText = !item.available || left === 0
                    ? 'Sold out'
                    : left === null ? 'Available' : `${left} left`;
                const photo = item.photo
                    ? `<img class="thumb" alt="" src="${escapeHtml(item.photo)}">`
                    : '<div class="thumb" aria-hidden="true"></div>';

                return `<button class="item" type="button" data-item="${item.id}"${unavailable ? ' disabled' : ''}>
                    ${photo}<b>${escapeHtml(item.name)}</b>
                    <small>${escapeHtml(item.category)} · ${stockText}</small>
                    <div class="p">${money(item.price)}</div>
                </button>`;
            }).join('')
            : '<p class="mute">No items match.</p>';
    };

    const renderHeld = () => {
        const box = document.querySelector('#heldBox');
        box.classList.toggle('hide', held.length === 0);
        box.innerHTML = held.length
            ? `<div class="mute" style="font-weight:600;margin-bottom:4px">On hold (${held.length})</div>`
                + held.map((draft, index) => {
                    const quantity = [...draft.cart.values()].reduce((sum, value) => sum + value, 0);
                    const subtotal = [...draft.cart].reduce((sum, [id, value]) => sum + itemById(id).price * value, 0);

                    return `<div class="ord" style="display:flex;justify-content:space-between;align-items:center;gap:6px">
                        <span>${escapeHtml(draft.customer || `Hold ${index + 1}`)}${draft.table ? ` · Table ${escapeHtml(draft.table)}` : ''}
                            <br><small class="mute">${quantity} items · ${money(subtotal)}</small></span>
                        <span><button class="btn sm red" type="button" data-resume="${index}">Resume</button>
                        <button class="btn sm" type="button" data-discard="${index}">Discard</button></span>
                    </div>`;
                }).join('')
            : '';
    };

    const renderCart = () => {
        const lines = [...cart].filter(([, quantity]) => quantity > 0);
        document.querySelector('#lines').innerHTML = lines.length
            ? lines.map(([id, quantity]) => {
                const item = itemById(id);

                return `<div class="line"><div><b>${escapeHtml(item.name)}</b><div class="mute">${money(item.price)}</div></div>
                    <span class="q"><button type="button" data-change="-1" data-id="${id}" aria-label="Remove one">−</button>
                    <b>${quantity}</b><button type="button" data-change="1" data-id="${id}" aria-label="Add one">+</button></span>
                    <b>${money(item.price * quantity)}</b></div>`;
            }).join('')
            : '<p class="mute">Tap a menu item to add it.</p>';

        const amount = totals();
        const type = document.querySelector('#dtype').value;
        const isIdDiscount = type === 'SC' || type === 'PWD';
        const cashTendered = Number(document.querySelector('#tend').value) || 0;
        const cashChange = Math.max(0, cashTendered - amount.total);
        const stockValid = lines.every(([id, quantity]) => {
            const item = itemById(id);
            return item.available && (item.stock === null || quantity <= item.stock);
        });
        document.querySelector('#tSub').textContent = money(amount.subtotal);
        document.querySelector('#tDisc').textContent = `−${money(amount.discount)}`;
        document.querySelector('#tEx').textContent = `−${money(amount.exemption)}`;
        document.querySelector('#rEx').classList.toggle('hide', amount.exemption <= 0);
        document.querySelector('#tVat').textContent = money(amount.vat);
        document.querySelector('#tTot').textContent = money(amount.total);
        document.querySelector('#dcust').classList.toggle('hide', type !== 'CUSTOM');
        document.querySelector('#did').classList.toggle('hide', !isIdDiscount);
        document.querySelector('#dhint').textContent = isIdDiscount
            ? '20% off the VAT-exempt price. Full name and ID number are required; TIN is optional.'
            : '';
        document.querySelector('#disc').required = type === 'CUSTOM';
        document.querySelector('#idName').required = isIdDiscount;
        document.querySelector('#idNo').required = isIdDiscount;
        document.querySelector('#cashBox').classList.toggle('hide', paymentMethod !== 'Cash');
        document.querySelector('#tend').required = paymentMethod === 'Cash';
        document.querySelector('#chg').textContent = money(cashChange);
        document.querySelector('#tbl').classList.toggle('hide', orderType !== 'Dine-in');
        document.querySelector('#tbl').required = orderType === 'Dine-in';
        document.querySelector('#hold').disabled = lines.length === 0;
        document.querySelector('#place').disabled = lines.length === 0
            || !stockValid
            || amount.total <= 0
            || (paymentMethod === 'Cash' && cents(cashTendered) < amount.total)
            || (orderType === 'Dine-in' && !document.querySelector('#tbl').value.trim())
            || !idDiscountIsValid();
        document.querySelector('#quick').innerHTML = [
            ['Exact', amount.total.toFixed(2)],
            ['100', 100],
            ['500', 500],
            ['1000', 1000],
        ].map(([label, value]) => `<button class="btn sm" type="button" data-tender="${value}">${label}</button>`).join('');
        renderHeld();
        renderMenu();
    };

    document.querySelector('#menu').addEventListener('click', (event) => {
        const button = event.target.closest('[data-item]');
        if (!button) return;
        const item = itemById(button.dataset.item);
        const current = cart.get(item.id) || 0;

        if (item.stock !== null && current >= item.stock) {
            toast(`${item.name} is sold out`);
            return;
        }
        cart.set(item.id, Math.min(20, current + 1));
        renderCart();
    });
    document.querySelector('#cats').addEventListener('click', (event) => {
        const button = event.target.closest('[data-category]');
        if (!button) return;
        category = button.dataset.category;
        renderMenu();
    });
    document.querySelector('#lines').addEventListener('click', (event) => {
        const button = event.target.closest('[data-change]');
        if (!button) return;
        const id = Number(button.dataset.id);
        const next = (cart.get(id) || 0) + Number(button.dataset.change);
        if (next <= 0) cart.delete(id);
        else if (next > 20 || (itemById(id).stock !== null && next > itemById(id).stock)) toast(`Only ${itemById(id).stock} left`);
        else cart.set(id, next);
        renderCart();
    });
    document.querySelector('#heldBox').addEventListener('click', (event) => {
        const discard = event.target.closest('[data-discard]');
        const resume = event.target.closest('[data-resume]');
        if (discard) {
            held.splice(Number(discard.dataset.discard), 1);
            renderCart();
        } else if (resume) {
            const draft = held.splice(Number(resume.dataset.resume), 1)[0];
            if (cart.size) held.push({ cart: new Map(cart), type: orderType, table: document.querySelector('#tbl').value, customer: document.querySelector('#cust').value });
            cart.clear();
            draft.cart.forEach((quantity, id) => cart.set(id, quantity));
            orderType = draft.type;
            document.querySelector('#order-type').value = orderType === 'Dine-in' ? 'dine_in' : 'take_out';
            document.querySelector('#payment-method').value = paymentMethod.toLocaleLowerCase();
            document.querySelectorAll('#otype [data-o]').forEach((button) => button.classList.toggle('on', button.dataset.o === orderType));
            document.querySelectorAll('#pay [data-p]').forEach((button) => button.classList.toggle('on', button.dataset.p === paymentMethod));
            document.querySelector('#tbl').value = draft.table;
            document.querySelector('#cust').value = draft.customer;
            document.querySelector('#otype').querySelectorAll('[data-o]').forEach((button) => button.classList.toggle('on', button.dataset.o === orderType));
            renderCart();
        }
    });
    document.querySelector('#otype').addEventListener('click', (event) => {
        const button = event.target.closest('[data-o]');
        if (!button) return;
        orderType = button.dataset.o;
        document.querySelector('#order-type').value = orderType === 'Dine-in' ? 'dine_in' : 'take_out';
        document.querySelectorAll('#otype [data-o]').forEach((option) => option.classList.toggle('on', option === button));
        renderCart();
    });
    document.querySelector('#pay').addEventListener('click', (event) => {
        const button = event.target.closest('[data-p]');
        if (!button) return;
        paymentMethod = button.dataset.p;
        document.querySelector('#payment-method').value = paymentMethod.toLocaleLowerCase();
        document.querySelectorAll('#pay [data-p]').forEach((option) => option.classList.toggle('on', option === button));
        renderCart();
    });
    document.querySelector('#quick').addEventListener('click', (event) => {
        const button = event.target.closest('[data-tender]');
        if (!button) return;
        document.querySelector('#tend').value = button.dataset.tender;
        renderCart();
    });
    document.querySelector('#q').addEventListener('input', renderMenu);
    ['tbl', 'cust', 'tend', 'disc', 'dtype', 'idName', 'idNo', 'tin'].forEach((id) => {
        document.querySelector(`#${id}`).addEventListener('input', renderCart);
        document.querySelector(`#${id}`).addEventListener('change', renderCart);
    });
    document.querySelector('#clear').addEventListener('click', () => {
        cart.clear();
        form.reset();
        orderType = 'Dine-in';
        paymentMethod = 'Cash';
        document.querySelector('#order-type').value = 'dine_in';
        document.querySelector('#payment-method').value = 'cash';
        document.querySelectorAll('#otype [data-o]').forEach((button) => button.classList.toggle('on', button.dataset.o === orderType));
        document.querySelectorAll('#pay [data-p]').forEach((button) => button.classList.toggle('on', button.dataset.p === paymentMethod));
        renderCart();
    });
    document.querySelector('#hold').addEventListener('click', () => {
        if (!cart.size) return;
        held.push({
            cart: new Map(cart),
            type: orderType,
            table: document.querySelector('#tbl').value.trim(),
            customer: document.querySelector('#cust').value.trim(),
        });
        cart.clear();
        document.querySelector('#tend').value = '';
        document.querySelector('#tbl').value = '';
        document.querySelector('#cust').value = '';
        renderCart();
    });
    document.querySelector('#phBtn').addEventListener('click', () => {
        toast('Edit menu items in Menu Management.');
    });
    document.querySelector('#cuBtn').addEventListener('click', () => {
        toast('Custom items are not saved to the menu.');
    });
    renderCart();

    form.addEventListener('submit', (event) => {
        const amount = totals();
        const tender = Number(document.querySelector('#tend').value) || 0;

        if (amount.total <= 0 || (paymentMethod === 'Cash' && cents(tender) < amount.total)) {
            event.preventDefault();
            toast('Enter enough cash to cover the order total.');
            return;
        }

        if (!idDiscountIsValid()) {
            event.preventDefault();
            toast('Enter the cardholder name and ID number.');
            return;
        }

        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }

        const hiddenItems = document.querySelector('#item-inputs');
        hiddenItems.replaceChildren();
        cart.forEach((quantity, id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `items[${id}]`;
            input.value = String(quantity);
            hiddenItems.append(input);
        });
        document.querySelector('#place').disabled = true;
        document.querySelector('#place').textContent = 'Placing order…';
    });
});
