/**
 * Small progressive enhancements for the Clinical Clean UI.
 * Every feature is opt-in through a data attribute and the page works without JavaScript.
 */
const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ count-up numbers
 * <span data-countup>UGX 8,420,000</span> counts from 0 to the number already in the text,
 * keeping any prefix/suffix and decimals. Screen readers get the final value via aria-label.
 */
const NUMBER = /-?\d[\d,]*(\.\d+)?/;

export function countUp(el, duration = 1200) {
    const finalText = el.dataset.countupFinal ?? el.textContent.trim();
    el.dataset.countupFinal = finalText;
    const match = finalText.match(NUMBER);
    if (!match) return;

    const target = parseFloat(match[0].replace(/,/g, ''));
    const decimals = match[1] ? match[1].length - 1 : 0;
    const useGrouping = match[0].includes(',') || Math.abs(target) >= 10000;
    const before = finalText.slice(0, match.index);
    const after = finalText.slice(match.index + match[0].length);

    el.setAttribute('aria-label', finalText);
    if (reduceMotion() || !Number.isFinite(target) || target === 0) { el.textContent = finalText; return; }

    cancelAnimationFrame(Number(el.dataset.countupRaf || 0));
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        const value = (target * eased).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping });
        el.textContent = t < 1 ? before + value + after : finalText;
        if (t < 1) el.dataset.countupRaf = String(requestAnimationFrame(tick));
    };
    el.dataset.countupRaf = String(requestAnimationFrame(tick));
}

function initCountUp() {
    const els = document.querySelectorAll('[data-countup]');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) { els.forEach((el) => countUp(el)); return; }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            io.unobserve(entry.target);
            countUp(entry.target, Number(entry.target.dataset.countup) || 1200);
        });
    }, { threshold: 0.4 });
    els.forEach((el) => io.observe(el));
}

/* ------------------------------------------------------------------ filter tabs
 * <div data-filter-tabs="#queue"><button data-filter="all" aria-pressed="true">…</button><button data-filter="waiting">…</button></div>
 * Rows inside #queue carry data-filter-value="waiting"; rows that don't match get [hidden].
 */
function initFilterTabs() {
    document.querySelectorAll('[data-filter-tabs]').forEach((group) => {
        const target = document.querySelector(group.dataset.filterTabs);
        if (!target) return;
        const buttons = group.querySelectorAll('[data-filter]');
        const empty = target.querySelector('[data-filter-empty]');

        buttons.forEach((btn) => btn.addEventListener('click', () => {
            const value = btn.dataset.filter;
            buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
            let shown = 0;
            target.querySelectorAll('[data-filter-value]').forEach((row) => {
                const match = value === 'all' || row.dataset.filterValue.split(' ').includes(value);
                row.hidden = !match;
                if (match) shown++;
            });
            // the "now" marker only makes sense in the unfiltered list
            target.querySelectorAll('[data-filter-hide-when-filtered]').forEach((el) => { el.hidden = value !== 'all'; });
            if (empty) empty.hidden = shown > 0;
        }));
    });
}

/* ------------------------------------------------------------------ home page availability
 * Department buttons swap which row is highlighted and fill the "next opening" panel.
 */
function initAvailability() {
    const root = document.querySelector('[data-availability]');
    if (!root) return;
    let data;
    try { data = JSON.parse(root.querySelector('script[type="application/json"]').textContent); } catch { return; }

    const buttons = root.querySelectorAll('[data-dept]');
    const rows = root.querySelectorAll('[data-dept-row]');
    const out = (name) => root.querySelector(`[data-out="${name}"]`);

    const select = (index) => {
        const d = data[index];
        if (!d) return;
        buttons.forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.dept) === index)));
        rows.forEach((r) => {
            const on = Number(r.dataset.deptRow) === index;
            r.classList.toggle('is-on', on);
            r.classList.toggle('is-dim', !on);
        });
        out('when').textContent = d.next ? d.next.when : 'No free slots this week';
        out('doctor').textContent = d.next ? `${d.next.doctor} · ${d.name}` : d.name;
        out('fee').textContent = d.fee;
        out('total').textContent = `${d.total} ${d.total === 1 ? 'slot' : 'slots'}`;
    };

    buttons.forEach((b) => b.addEventListener('click', () => select(Number(b.dataset.dept))));
    select(0);
}

export function initUi() {
    window.ccCountUp = (el) => countUp(el, 700);
    initCountUp();
    initFilterTabs();
    initAvailability();
}
