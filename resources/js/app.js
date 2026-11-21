import './bootstrap';
import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import { initCharts } from './charts';
import { initSlotPicker } from './slot-picker';
import { initUi } from './ui';

window.bootstrap = bootstrap;
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltips & toasts
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    document.querySelectorAll('.toast').forEach((el) => new bootstrap.Toast(el, { delay: 5000 }).show());

    // Confirm-before-submit for destructive actions: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    // Prevent double submissions: once a POST/PUT/PATCH/DELETE form is submitted, lock its buttons.
    // (On this setup a request can take a second or two; a second click used to re-send the form,
    // e.g. registering the same patient twice -> "email already taken".)
    document.querySelectorAll('form').forEach((form) => {
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return; // report downloads etc.
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return; // e.g. user cancelled a confirm() dialog
            if (form.dataset.submitting) { e.preventDefault(); return; }
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
                btn.disabled = true;
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
            });
        });
    });
    // If the user navigates back to a page from the browser cache, unlock its forms again.
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-submitting]').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('button[data-original-html]').forEach((btn) => { btn.disabled = false; btn.innerHTML = btn.dataset.originalHtml; });
        });
    });

    // Auto-submit filter selects
    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    // Profile photo preview
    document.querySelectorAll('[data-photo-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const [file] = input.files;
            const target = document.querySelector(input.dataset.photoInput);
            if (file && target) target.src = URL.createObjectURL(file);
        });
    });

    initUi();
    initCharts();
    initSlotPicker();
});
