document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    const toggle = event.target.closest('[data-dashboard-series-toggle]');
    if (!toggle) return;

    const chart = document.getElementById(toggle.dataset.chartTarget);
    if (!chart) return;

    const series = chart.querySelector('[data-chart-series="revenue"]');
    if (!series) return;

    const visible = toggle.getAttribute('aria-pressed') !== 'true';
    toggle.setAttribute('aria-pressed', String(visible));
    series.toggleAttribute('hidden', !visible);
});
