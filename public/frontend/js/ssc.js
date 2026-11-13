// Small page helpers (no inline scripts, to keep the CSP strict).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.textContent = input.type === 'password' ? 'Show' : 'Hide';
        });
    });
    document.querySelectorAll('input[data-digits-only]').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, ''); });
    });
});
