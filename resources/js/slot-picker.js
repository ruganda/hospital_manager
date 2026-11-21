/**
 * Dynamic appointment slot loading + real-time doctor availability (AJAX).
 * Markup: <div data-slot-picker data-url-template=".../ajax/doctors/__ID__/slots" data-ignore="appointmentId">
 *   select[data-doctor], input[data-date], input[type=hidden][data-slot-value], [data-slots], [data-doctor-info]
 */
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function initSlotPicker() {
    document.querySelectorAll('[data-slot-picker]').forEach((wrap) => {
        const doctor = wrap.querySelector('[data-doctor]');
        const date = wrap.querySelector('[data-date]');
        const hidden = wrap.querySelector('[data-slot-value]');
        const box = wrap.querySelector('[data-slots]');
        const info = wrap.querySelector('[data-doctor-info]');
        let current = hidden.value;

        const render = (data) => {
            if (info) {
                info.innerHTML = `
                    <dl class="cc-dl small" style="grid-template-columns: 90px minmax(0,1fr); row-gap: 6px">
                        <dt>Department</dt><dd>${escapeHtml(data.doctor.department)}</dd>
                        <dt>Fee</dt><dd class="fw-semibold">${escapeHtml(data.doctor.fee)}</dd>
                        <dt>Consults</dt><dd>${data.doctor.working_days.map(escapeHtml).join('<br>') || 'No schedule defined'}</dd>
                    </dl>`;
            }

            if (!data.works_today) {
                box.innerHTML = `<div class="cc-callout warn"><i class="fa-solid fa-calendar-xmark"></i><div>${escapeHtml(data.doctor.name)} does not consult on ${escapeHtml(data.day)}. Pick another date.</div></div>`;
                hidden.value = '';
                return;
            }

            const header = `<div class="d-flex flex-wrap justify-content-between gap-2 mb-3 small">
                <span class="fw-semibold" style="color:var(--cc-ink)">${escapeHtml(data.day)}</span>
                <span class="cc-badge ${data.available_count ? 'success' : 'danger'}">${data.available_count} free</span></div>`;

            const buttons = data.slots.map((s) => {
                if (!s.available) {
                    return `<button type="button" class="cc-slot ${s.reason === 'booked' ? 'booked' : ''}" disabled title="${s.reason === 'booked' ? 'Already booked' : 'Time has passed'}" aria-label="${s.start} ${s.reason === 'booked' ? 'already booked' : 'has passed'}">${s.start}</button>`;
                }
                const active = s.start === current;
                return `<button type="button" class="cc-slot ${active ? 'selected' : ''}" aria-pressed="${active}" data-slot="${s.start}" data-end="${s.end}">${s.start}</button>`;
            }).join('');

            box.innerHTML = header + `<div class="slot-grid">${buttons}</div>
                <div class="cc-legend mt-3"><span><i class="sw" style="background:#fff;border:1px solid #cfd6df"></i>Free</span><span><i class="sw" style="background:#0b6e6e"></i>Selected</span><span><i class="sw" style="background:#f6f7f9;border:1px solid #e6e8ec"></i>Booked or past</span></div>`;

            if (current && !data.slots.some((s) => s.available && s.start === current)) {
                current = '';
                hidden.value = '';
            }

            box.querySelectorAll('[data-slot]').forEach((b) => b.addEventListener('click', () => {
                current = b.dataset.slot;
                hidden.value = current;
                box.querySelectorAll('[data-slot]').forEach((x) => { x.classList.remove('selected'); x.setAttribute('aria-pressed', 'false'); });
                b.classList.add('selected');
                b.setAttribute('aria-pressed', 'true');
            }));
        };

        const load = async () => {
            if (!doctor.value || !date.value) {
                box.innerHTML = '<div class="cc-empty py-4"><div class="ic"><i class="fa-regular fa-clock"></i></div>Choose a doctor and a date to see free times.</div>';
                if (info) info.innerHTML = '';
                return;
            }
            box.innerHTML = '<p class="text-muted small mb-0"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Checking availability…</p>';
            try {
                const url = wrap.dataset.urlTemplate.replace('__ID__', doctor.value);
                const { data } = await window.axios.get(url, { params: { date: date.value, ignore: wrap.dataset.ignore || undefined } });
                render(data);
            } catch (e) {
                box.innerHTML = '<div class="cc-callout danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Could not load slots. Please try again.</div></div>';
            }
        };

        doctor.addEventListener('change', () => { current = ''; hidden.value = ''; load(); });
        date.addEventListener('change', () => { current = ''; hidden.value = ''; load(); });
        load();
    });
}
