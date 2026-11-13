document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });
    document.querySelectorAll('input[data-digits-only]').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, ''); });
    });
});
