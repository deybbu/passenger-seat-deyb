import './admin-dashboard.js';
import './admin-inventory.js';
import './pos-register.js';
import './inventory-scanner.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function showClientStatus(message, isError = false) {
    const status = document.getElementById('adminClientStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('error', isError);
    status.hidden = false;
}

function toggleSidebar(forceClose = false) {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminOverlay');
    const shell = document.querySelector('.admin-shell');
    const toggle = document.getElementById('adminSidebarToggle');
    if (!sidebar || !overlay || !shell || !toggle) return;

    if (window.innerWidth > 1100) {
        if (!forceClose) {
            const collapsed = shell.classList.toggle('is-sidebar-collapsed');
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            toggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
            try {
                localStorage.setItem('passenger-seat-admin-sidebar-collapsed', String(collapsed));
            } catch {
                showClientStatus('Navigation changed, but this browser could not save your preference.', true);
            }
        }
        return;
    }

    const open = forceClose ? false : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    toggle.title = open ? 'Close navigation' : 'Open navigation';
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

function applyAdminTheme(theme, persist = false) {
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    document.querySelectorAll('[data-admin-theme]').forEach((button) => {
        const active = button.dataset.adminTheme === theme;
        button.setAttribute('aria-pressed', String(active));
    });

    if (persist) {
        try {
            localStorage.setItem('ps-app-theme', theme);
        } catch {
            showClientStatus('Theme changed, but this browser could not save your preference.', true);
        }
    }
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
    const sidebarNav = document.getElementById('nav');
    const sidebarScrollKey = 'passenger-seat-admin-sidebar-scroll';
    const shell = document.querySelector('.admin-shell');
    const sidebarToggle = document.getElementById('adminSidebarToggle');
    if (shell && sidebarToggle && window.innerWidth > 1100) {
        try {
            const collapsed = localStorage.getItem('passenger-seat-admin-sidebar-collapsed') === 'true';
            shell.classList.toggle('is-sidebar-collapsed', collapsed);
            sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
        } catch {
            showClientStatus('Navigation preference could not be loaded in this browser.', true);
        }
    } else if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.setAttribute('aria-label', 'Open navigation');
        sidebarToggle.title = 'Open navigation';
    }
    if (sidebarNav) {
        const savedScroll = Number.parseInt(sessionStorage.getItem(sidebarScrollKey) || '0', 10);
        if (Number.isFinite(savedScroll)) {
            requestAnimationFrame(() => {
                sidebarNav.scrollTop = savedScroll;
            });
        }
        const saveSidebarScroll = () => {
            sessionStorage.setItem(sidebarScrollKey, String(sidebarNav.scrollTop));
        };
        sidebarNav.addEventListener('scroll', saveSidebarScroll, { passive: true });
        document.querySelectorAll('#nav a').forEach((link) => {
            link.addEventListener('click', saveSidebarScroll);
        });
    }

    const initialTheme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    applyAdminTheme(initialTheme);
    document.querySelectorAll('[data-admin-theme]').forEach((button) => {
        button.addEventListener('click', () => {
            const theme = button.dataset.adminTheme;
            if (theme === 'light' || theme === 'dark') applyAdminTheme(theme, true);
        });
    });

    document.getElementById('adminSidebarToggle')?.addEventListener('click', () => toggleSidebar());
    document.getElementById('adminOverlay')?.addEventListener('click', () => toggleSidebar(true));

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1100) {
            toggleSidebar(true);
            const collapsed = shell?.classList.contains('is-sidebar-collapsed') ?? false;
            sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
            sidebarToggle?.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            if (sidebarToggle) sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
        } else {
            const open = document.getElementById('adminSidebar')?.classList.contains('open') ?? false;
            sidebarToggle?.setAttribute('aria-expanded', String(open));
            sidebarToggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            if (sidebarToggle) sidebarToggle.title = open ? 'Close navigation' : 'Open navigation';
        }
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

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        toggleSidebar(true);
        if (!panel || panel.hasAttribute('hidden')) return;
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
