const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function showClientStatus(message, isError = false) {
    const status = document.getElementById('adminClientStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('error', isError);
    status.hidden = false;
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem('ps-admin-theme', theme);
    } catch (error) {
        showClientStatus('The theme changed, but this browser could not save your preference.', true);
    }
}

function toggleSidebar(forceClose = false) {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminOverlay');
    if (!sidebar || !overlay) return;
    const open = forceClose ? false : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    document.getElementById('adminSidebarToggle')?.setAttribute('aria-expanded', String(open));
}

function setUnread(count) {
    const badge = document.getElementById('adminNotifyBadge');
    const navBadge = document.getElementById('adminNotifyNavBadge');
    [badge, navBadge].forEach((element) => {
        if (!element) return;
        element.textContent = String(count);
        element.hidden = count < 1;
    });
}

async function postAlert(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        throw new Error(`Request failed (${response.status}). Please try again.`);
    }
    return response.json();
}

function markItemsRead(items) {
    items.forEach((item) => {
        item.classList.remove('unread');
        item.querySelectorAll('.admin-notify-read').forEach((button) => button.remove());
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('adminSidebarToggle')?.addEventListener('click', () => toggleSidebar());
    document.getElementById('adminOverlay')?.addEventListener('click', () => toggleSidebar(true));

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) toggleSidebar(true);
    });

    document.getElementById('adminThemeBtn')?.addEventListener('click', () => {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });

    const panel = document.getElementById('adminNotifyPanel');
    const notifyBtn = document.getElementById('adminNotifyBtn');
    notifyBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!panel) return;
        const open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        notifyBtn.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', (event) => {
        if (!panel || panel.hasAttribute('hidden')) return;
        if (!(event.target instanceof Element) || event.target.closest('.admin-notify-wrap')) return;
        panel.setAttribute('hidden', '');
        notifyBtn?.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('click', async (event) => {
        if (!(event.target instanceof Element)) return;
        const markAll = event.target.closest('[data-mark-all-read]');
        const markOne = event.target.closest('.admin-notify-read');
        const button = markAll || markOne;
        if (!button) return;
        event.preventDefault();

        try {
            const data = await postAlert(button.dataset.url, markOne ? { key: button.dataset.key } : {});
            setUnread(data.unread ?? 0);
            if (markAll) {
                markItemsRead(document.querySelectorAll('.admin-notify-item'));
            } else if (markOne) {
                markItemsRead(document.querySelectorAll(`.admin-notify-item[data-key="${CSS.escape(button.dataset.key)}"]`));
            }
            showClientStatus(markAll ? 'All notifications marked as read.' : 'Notification marked as read.');
        } catch (error) {
            showClientStatus(error.message, true);
        }
    });
});
