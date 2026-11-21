import Chart from 'chart.js/auto';

// Clinical Clean palette: teal is the accent, everything else is muted.
const palette = {
    primary: '#0b6e6e', soft: '#c7dedc', info: '#3b6fd4', success: '#7cb68a', danger: '#c8553d',
    warning: '#d9922e', secondary: '#a3acb9', purple: '#7c5cc4', dark: '#334155', teal: '#0b6e6e',
};
const series = [palette.primary, palette.secondary, palette.warning, palette.info, palette.purple, palette.success, palette.danger, palette.dark];

Chart.defaults.font.size = 12;
Chart.defaults.color = '#5a6678';
Chart.defaults.borderColor = '#eef0f3';
Chart.defaults.plugins.tooltip.backgroundColor = '#142033';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.boxPadding = 4;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.boxHeight = 8;

const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const fmtMoney = (currency) => (v) => `${currency} ${Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
const charts = {};

function buildConfig(spec) {
    const opts = spec.options || {};
    const circular = ['pie', 'doughnut'].includes(spec.type);
    const named = (opts.colors || []).map((c) => palette[c] || c);

    const datasets = spec.datasets.map((ds, i) => {
        if (circular) {
            return { ...ds, backgroundColor: named.length ? named : series, borderWidth: 2, borderColor: '#fff', hoverOffset: 4 };
        }
        const color = named[i] || series[i % series.length];
        const line = spec.type === 'line';
        return {
            ...ds,
            backgroundColor: line ? (i === 0 ? 'rgba(11, 110, 110, .08)' : 'transparent') : color,
            borderColor: color,
            borderWidth: line ? 2 : 0,
            borderDash: line && i > 0 ? [4, 4] : [],
            borderRadius: spec.type === 'bar' ? 4 : 0,
            maxBarThickness: opts.horizontal ? 14 : 32,
            fill: line && i === 0,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
        };
    });

    const money = opts.currency ? fmtMoney(opts.currency) : null;

    const config = {
        type: spec.type === 'pie' ? 'doughnut' : spec.type,
        data: { labels: spec.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500, easing: 'easeOutCubic' },
            interaction: { mode: circular ? 'nearest' : 'index', intersect: circular },
            plugins: {
                legend: { display: circular || datasets.length > 1, position: circular ? 'bottom' : 'top', align: circular ? 'center' : 'end' },
                tooltip: money ? { callbacks: { label: (c) => ` ${c.dataset.label || c.label}: ${money(c.parsed.y ?? c.parsed.x ?? c.parsed)}` } } : {},
            },
        },
    };

    if (circular) {
        config.options.cutout = '68%';
    } else {
        const valueAxis = { beginAtZero: true, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 6, ...(money ? { callback: money } : {}) }, grid: { color: '#eef0f3' }, stacked: !!opts.stacked };
        const catAxis = { grid: { display: false }, border: { color: '#e6e8ec' }, stacked: !!opts.stacked, ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 12 } };
        if (opts.horizontal) {
            config.options.indexAxis = 'y';
            config.options.scales = { x: valueAxis, y: catAxis };
        } else {
            config.options.scales = { x: catAxis, y: valueAxis };
        }
    }
    return config;
}

/** Totals shown next to the appointments chart (booked / completed / completion rate). */
function updateSummary(key, spec) {
    const box = document.querySelector(`[data-chart-summary="${key}"]`);
    if (!box || !spec) return;
    const sum = (i) => (spec.datasets[i]?.data || []).reduce((a, b) => a + Number(b || 0), 0);
    const booked = sum(0);
    const completed = sum(1);
    const values = {
        booked: booked.toLocaleString('en-US'),
        completed: completed.toLocaleString('en-US'),
        rate: (booked ? Math.round((completed / booked) * 100) : 0) + '%',
    };
    Object.entries(values).forEach(([name, text]) => {
        const el = box.querySelector(`[data-summary="${name}"]`);
        if (el) { delete el.dataset.countupFinal; el.textContent = text; window.ccCountUp?.(el); }
    });
}

function draw(canvas, spec) {
    const key = canvas.dataset.chart;
    canvas.closest('.chart-box')?.querySelector('.chart-loading')?.remove();
    if (!spec) return;
    if (charts[key]) charts[key].destroy();
    charts[key] = new Chart(canvas, buildConfig(spec));
    updateSummary(key, spec);
}

/** Fetches chart JSON once (AJAX) and draws every <canvas data-chart="key">. */
export async function initCharts() {
    Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--cc-font').trim() || '"Geist", system-ui, sans-serif';
    const canvases = document.querySelectorAll('canvas[data-chart]');
    if (!canvases.length) return;

    const url = document.querySelector('[data-charts-url]')?.dataset.chartsUrl;
    if (!url) return;

    try {
        const { data } = await window.axios.get(url);
        canvases.forEach((canvas) => draw(canvas, data[canvas.dataset.chart]));
    } catch (e) {
        canvases.forEach((c) => {
            const box = c.closest('.chart-box');
            box?.querySelector('.chart-loading')?.remove();
            box?.insertAdjacentHTML('beforeend', '<p class="text-muted small position-absolute top-50 start-50 translate-middle m-0">Could not load chart data.</p>');
        });
    }

    // Range switch: <div data-chart-range="appointmentsTrend"><button data-days="7">…</button></div>
    document.querySelectorAll('[data-chart-range]').forEach((group) => {
        const key = group.dataset.chartRange;
        const canvas = document.querySelector(`canvas[data-chart="${key}"]`);
        group.querySelectorAll('button[data-days]').forEach((btn) => btn.addEventListener('click', async () => {
            if (btn.getAttribute('aria-pressed') === 'true') return;
            group.querySelectorAll('button[data-days]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
            group.setAttribute('aria-busy', 'true');
            try {
                const { data } = await window.axios.get(url, { params: { only: key, days: btn.dataset.days } });
                if (canvas) draw(canvas, data[key]);
                const label = document.querySelector(`[data-chart-range-label="${key}"]`);
                if (label) label.textContent = btn.dataset.label || '';
            } catch (e) {
                /* keep the current chart */
            } finally {
                group.removeAttribute('aria-busy');
            }
        }));
    });
}
