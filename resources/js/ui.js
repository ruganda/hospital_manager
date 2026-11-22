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

/* ------------------------------------------------------------------ Mobile Money
 * Pending requests carry data-momo-poll="<status url>": poll every 4 s and reload once settled.
 * The phone field (data-momo-phone) pre-selects MTN / Airtel from the number's prefix.
 */
function initMobileMoney() {
    const pending = document.querySelectorAll('[data-momo-poll]');
    if (pending.length) {
        const started = Date.now();
        const tick = async () => {
            if (Date.now() - started > 10 * 60 * 1000) return; // stop after 10 minutes; "Check now" still works
            for (const el of pending) {
                try {
                    const { data } = await window.axios.get(el.dataset.momoPoll);
                    if (!data.pending) { window.location.reload(); return; }
                    const msg = el.querySelector('[data-momo-message]');
                    if (msg && data.message) msg.textContent = data.message;
                } catch (e) { /* network hiccup: try again next tick */ }
            }
            setTimeout(tick, 4000);
        };
        setTimeout(tick, 4000);
    }

    // Every Mobile Money number field pre-selects its own form's network radio from the prefix.
    document.querySelectorAll('[data-momo-phone]').forEach((phone) => {
        const scope = phone.closest('form') || document;
        phone.addEventListener('input', () => {
            const digits = phone.value.replace(/\D/g, '');
            const local = digits.startsWith('256') ? '0' + digits.slice(3) : digits;
            const prefix = local.slice(0, 3);
            const net = ['076', '077', '078', '039', '031'].includes(prefix) ? 'MTN' : (['070', '074', '075', '020'].includes(prefix) ? 'AIRTEL' : null);
            const radio = net && scope.querySelector(`input[type=radio][name$="network"][value="${net}"]`);
            if (radio) radio.checked = true;
        });
    });

    // "Request payment on save" toggles its fields (consultation form).
    document.querySelectorAll('[data-toggle-target]').forEach((box) => {
        const target = document.querySelector(box.dataset.toggleTarget);
        if (!target) return;
        const sync = () => { target.hidden = !box.checked; };
        box.addEventListener('change', sync);
        sync();
    });
}

export function initUi() {
    initMobileMoney();
    window.ccCountUp = (el) => countUp(el, 700);
    initCountUp();
    initFilterTabs();
    initAvailability();
}
