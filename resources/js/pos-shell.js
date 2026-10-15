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
});
