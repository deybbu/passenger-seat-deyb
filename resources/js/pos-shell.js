document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('#tabs')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-url]');
        if (button) window.location.assign(button.dataset.url);
    });

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

    const alerts = document.querySelector('[data-pos-bill-alerts]');
    if (!alerts) return;

    const toggle = alerts.querySelector('[data-pos-bill-alert-toggle]');
    const panel = alerts.querySelector('[data-pos-bill-alert-panel]');
    const list = alerts.querySelector('[data-pos-bill-alert-list]');
    const muteButton = alerts.querySelector('[data-bill-alert-mute]');
    const region = document.querySelector('[data-pos-bill-request-region]');
    const cards = document.querySelector('[data-pos-bill-request-cards]');
    const seenKey = 'passenger-seat-bill-alerts-seen';
    let soundMuted = false;
    let refreshing = false;
    let toastTimer;

    try {
        soundMuted = localStorage.getItem('passenger-seat-bill-alerts-muted') === 'true';
    } catch (error) {
        console.error('Bill alert sound preference could not be loaded.', error);
    }

    const updateMuteButton = () => {
        if (!muteButton) return;
        muteButton.textContent = soundMuted ? 'Sound muted' : 'Mute sound';
        muteButton.setAttribute('aria-pressed', String(soundMuted));
    };

    const paymentLabel = (method) => ({
        cash: 'Cash',
        gcash: 'GCash',
        card: 'Card',
    })[method] || 'Payment preference not recorded';

    const formatMoney = (amount) => `₱${Number(amount || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const elapsed = (timestamp) => {
        const requestedAt = Date.parse(timestamp);
        if (!Number.isFinite(requestedAt)) return 'just now';
        const minutes = Math.max(0, Math.floor((Date.now() - requestedAt) / 60000));
        if (minutes < 1) return 'just now';
        if (minutes < 60) return `${minutes} min ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} hr ago`;
        return `${Math.floor(hours / 24)} day ago`;
    };

    const receiptIcon = () => {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('aria-hidden', 'true');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-width', '1.8');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        const rect = document.createElementNS(icon.namespaceURI, 'rect');
        rect.setAttribute('x', '4');
        rect.setAttribute('y', '3');
        rect.setAttribute('width', '16');
        rect.setAttribute('height', '18');
        rect.setAttribute('rx', '2');
        icon.append(rect);
        ['8', '12', '16'].forEach((y) => {
            const line = document.createElementNS(icon.namespaceURI, 'path');
            line.setAttribute('d', `M8 ${y}h8`);
            icon.append(line);
        });
        return icon;
    };

    const makeRequestCard = (request) => {
        const article = document.createElement('article');
        article.className = 'pos-bill-request-card';
        article.dataset.billRequestCard = String(request.id);
        const icon = receiptIcon();
        icon.classList.add('pos-bill-request-icon');
        article.append(icon);

        const content = document.createElement('div');
        content.className = 'pos-bill-request-content';
        const title = document.createElement('h3');
        title.textContent = `TABLE ${request.table_number} - READY FOR BILL OUT`;
        const summary = document.createElement('div');
        summary.className = 'pos-bill-request-summary';
        const method = document.createElement('span');
        method.className = 'pos-bill-method';
        method.textContent = paymentLabel(request.payment_method);
        const total = document.createElement('strong');
        total.textContent = formatMoney(request.total);
        summary.append(method, total);
        const details = document.createElement('p');
        details.textContent = `${request.order_count} orders · requested ${elapsed(request.requested_at)}`;
        const action = document.createElement('a');
        action.className = 'pos-bill-request-action';
        action.href = request.bill_url;
        action.textContent = 'Record terminal payment';
        content.append(title, summary, details, action);
        article.append(content);
        return article;
    };

    const makeNotification = (request) => {
        const item = document.createElement('article');
        item.className = 'pos-bill-alert-item';
        item.append(receiptIcon());
        const content = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `TABLE ${request.table_number} - READY FOR BILL OUT`;
        const details = document.createElement('p');
        details.textContent = `${paymentLabel(request.payment_method)} · ${formatMoney(request.total)} · ${request.order_count} orders · ${elapsed(request.requested_at)}`;
        const link = document.createElement('a');
        link.href = alerts.dataset.billingUrl;
        link.textContent = 'Open Billing';
        content.append(title, details, link);
        item.append(content);
        return item;
    };

    const playAlert = () => {
        if (soundMuted || !window.AudioContext) return;

        try {
            const context = new window.AudioContext();
            const oscillator = context.createOscillator();
            const volume = context.createGain();
            oscillator.frequency.value = 880;
            volume.gain.setValueAtTime(0.001, context.currentTime);
            volume.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.02);
            volume.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.22);
            oscillator.connect(volume);
            volume.connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.23);
            window.setTimeout(() => void context.close(), 300);
        } catch (error) {
            console.error('Bill request alert sound could not play.', error);
        }
    };

    const showBillToast = (request) => {
        if (!toast) return;
        toast.textContent = `TABLE ${request.table_number} - READY FOR BILL OUT · ${paymentLabel(request.payment_method)} · ${formatMoney(request.total)}`;
        toast.classList.add('on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('on'), 6000);
    };

    const updateRequests = (requests) => {
        const count = requests.length;
        document.querySelectorAll('[data-bill-request-count]').forEach((badge) => {
            badge.textContent = String(count);
            badge.hidden = count === 0;
        });

        if (cards) {
            cards.replaceChildren(...requests.map(makeRequestCard));
            if (region) region.hidden = count === 0;
        }
        if (list) {
            list.replaceChildren(...(requests.length
                ? requests.map(makeNotification)
                : [Object.assign(document.createElement('p'), {
                    className: 'pos-bill-alert-empty',
                    textContent: 'No bill requests right now.',
                })]));
        }

        let previouslySeen = [];
        try {
            previouslySeen = JSON.parse(sessionStorage.getItem(seenKey) || '[]');
            if (!Array.isArray(previouslySeen)) previouslySeen = [];
        } catch (error) {
            console.error('Previously seen bill alerts could not be loaded.', error);
        }
        const currentKeys = requests.map((request) => `${request.id}-${request.requested_at || ''}`);
        const newRequest = requests.find((request) => !previouslySeen.includes(`${request.id}-${request.requested_at || ''}`));
        try {
            sessionStorage.setItem(seenKey, JSON.stringify(currentKeys));
        } catch (error) {
            console.error('Bill alert state could not be saved.', error);
        }

        if (newRequest) {
            showBillToast(newRequest);
            playAlert();
        }
    };

    const refreshBillRequests = async () => {
        if (refreshing) return;
        refreshing = true;

        try {
            const response = await fetch(alerts.dataset.url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`Bill request refresh failed with HTTP ${response.status}.`);
            const result = await response.json();
            if (!Array.isArray(result.requests)) throw new Error('Bill request refresh returned an invalid response.');
            updateRequests(result.requests);
        } catch (error) {
            console.error(error);
            if (list) {
                list.replaceChildren(Object.assign(document.createElement('p'), {
                    className: 'pos-bill-alert-empty',
                    textContent: 'Bill alerts are temporarily unavailable. Retrying automatically.',
                }));
            }
        } finally {
            refreshing = false;
        }
    };

    toggle?.addEventListener('click', () => {
        if (!panel) return;
        const open = panel.hidden;
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    });

    muteButton?.addEventListener('click', () => {
        soundMuted = !soundMuted;
        updateMuteButton();
        try {
            localStorage.setItem('passenger-seat-bill-alerts-muted', String(soundMuted));
        } catch (error) {
            console.error('Bill alert sound preference could not be saved.', error);
            if (toast) {
                toast.textContent = 'Sound setting applies until this screen is closed.';
                toast.classList.add('on');
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!panel || panel.hidden || !(event.target instanceof Element)) return;
        if (event.target.closest('[data-pos-bill-alerts]')) return;
        panel.hidden = true;
        toggle?.setAttribute('aria-expanded', 'false');
    });

    updateMuteButton();
    void refreshBillRequests();
    window.setInterval(() => void refreshBillRequests(), 15000);
});
