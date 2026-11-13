// Payment pages: auto-submit the gateway form, print receipts, confirm before paying.
document.addEventListener('DOMContentLoaded', function () {
    var auto = document.querySelector('form[data-autosubmit]');
    if (auto) {
        setTimeout(function () { auto.submit(); }, 800);
    }
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });
    document.querySelectorAll('form[data-pay-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type=submit]').forEach(function (b) {
                b.disabled = true;
                b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait…';
            });
        });
    });
});
