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
    // Registration: no copy, cut, paste or drag-and-drop, so each detail is really typed twice.
    document.querySelectorAll('form[data-no-paste]').forEach(function (form) {
        ['paste', 'copy', 'cut', 'drop'].forEach(function (evt) {
            form.addEventListener(evt, function (e) {
                if (e.target.matches('input')) {
                    e.preventDefault();
                    var hint = e.target.closest('.mb-3') && e.target.closest('.mb-3').querySelector('[data-match-msg]');
                    if (evt === 'paste' || evt === 'drop') {
                        e.target.setAttribute('title', 'Please type this in; pasting is turned off.');
                    }
                }
            });
        });
    });
    // "Re-enter" fields: show a mismatch as soon as the second entry is complete.
    document.querySelectorAll('input[data-match]').forEach(function (second) {
        var first = document.getElementById(second.getAttribute('data-match'));
        if (!first) return;
        var norm = function (v) { return second.type === 'email' ? v.trim().toLowerCase() : v.trim(); };
        var check = function () {
            var bad = second.value !== '' && norm(second.value) !== norm(first.value);
            second.classList.toggle('is-invalid', bad);
            if (bad === false && second.value !== '') { second.classList.add('is-valid'); } else { second.classList.remove('is-valid'); }
        };
        second.addEventListener('blur', check);
        second.addEventListener('input', function () { if (second.classList.contains('is-invalid') || second.value.length >= first.value.length) check(); });
        first.addEventListener('input', function () { if (second.value !== '') check(); });
    });
});
