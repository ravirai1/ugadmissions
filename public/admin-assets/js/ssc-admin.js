document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });
    // "Select all" checkbox: <input type="checkbox" data-check-all="name[]">
    document.querySelectorAll('input[data-check-all]').forEach(function (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('input[type=checkbox][name="' + all.getAttribute('data-check-all') + '"]').forEach(function (c) { c.checked = all.checked; });
        });
    });
    document.querySelectorAll('input[data-digits-only]').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, ''); });
    });
});
