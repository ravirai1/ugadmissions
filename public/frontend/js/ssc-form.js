// Application form helpers. No inline scripts (strict CSP).
(function () {
    'use strict';

    function valueOf(form, name) {
        var els = form.querySelectorAll('[name="' + name + '"]');
        var val = null;
        els.forEach(function (el) {
            if (el.type === 'checkbox') { if (el.checked) { val = el.value || 'on'; } }
            else if (el.type === 'radio') { if (el.checked) { val = el.value; } }
            else { val = el.value; }
        });
        return val;
    }

    // <div data-show-when="field=value|value2"> or data-show-when="field" (truthy / checked)
    function applyConditions(root) {
        root.querySelectorAll('[data-show-when]').forEach(function (box) {
            var rule = box.getAttribute('data-show-when');
            var parts = rule.split('=');
            var v = valueOf(root, parts[0]);
            var show = parts.length > 1 ? parts[1].split('|').indexOf(v || '') !== -1 : !!v;
            if (box.hasAttribute('data-hide')) { show = !show; }
            box.classList.toggle('d-none', !show);
            box.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (!el.closest('[data-show-when]') || el.closest('[data-show-when]') === box) { el.disabled = !show; }
            });
        });
    }

    function districts(form) {
        var data = document.getElementById('districts-data');
        if (!data) { return; }
        var map = JSON.parse(data.textContent || '{}');
        form.querySelectorAll('select[data-state-select]').forEach(function (stateSel) {
            var prefix = stateSel.getAttribute('data-state-select');
            var distSel = form.querySelector('select[name="' + prefix + '_district_id"]');
            var distOther = form.querySelector('[data-district-other="' + prefix + '"]');
            function refresh(keep) {
                var list = map[stateSel.value] || [];
                var current = keep ? distSel.getAttribute('data-current') : '';
                distSel.innerHTML = '<option value="">Select district</option>';
                list.forEach(function (d) {
                    var o = document.createElement('option');
                    o.value = d.id; o.textContent = d.name;
                    if (String(d.id) === String(current)) { o.selected = true; }
                    distSel.appendChild(o);
                });
                var hasList = list.length > 0;
                distSel.closest('.district-select').classList.toggle('d-none', !hasList);
                distOther.classList.toggle('d-none', hasList || !stateSel.value);
            }
            stateSel.addEventListener('change', function () { refresh(false); });
            refresh(true);
        });
    }

    function repeaters(form) {
        form.querySelectorAll('[data-repeater]').forEach(function (rep) {
            var tpl = rep.querySelector('template');
            var body = rep.querySelector('[data-rows]');
            var max = parseInt(rep.getAttribute('data-max') || '10', 10);
            var next = body.querySelectorAll('[data-row]').length + 100;
            rep.querySelector('[data-add-row]').addEventListener('click', function () {
                if (body.querySelectorAll('[data-row]').length >= max) { return; }
                var html = tpl.innerHTML.replace(/__INDEX__/g, String(next++));
                body.insertAdjacentHTML('beforeend', html);
                recalc(form); cuetMatch(form);
            });
            body.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-remove-row]');
                if (btn) { btn.closest('[data-row]').remove(); recalc(form); }
            });
        });
    }

    // Class 12 aggregate percentage preview.
    function recalc(form) {
        var out = form.querySelector('[data-percentage]');
        if (!out) { return; }
        var max = 0, got = 0;
        form.querySelectorAll('[data-row]').forEach(function (row) {
            var m = parseFloat((row.querySelector('[data-max]') || {}).value);
            var o = parseFloat((row.querySelector('[data-obtained]') || {}).value);
            if (!isNaN(m) && !isNaN(o)) { max += m; got += o; }
        });
        out.textContent = max > 0 ? (got / max * 100).toFixed(2) + '%' : '–';
    }

    // CUET paper ↔ Class 12 subject match indicator.
    function cuetMatch(form) {
        form.querySelectorAll('select[data-cuet-subject]').forEach(function (sel) {
            var cell = sel.closest('[data-row]').querySelector('[data-match]');
            var opt = sel.options[sel.selectedIndex];
            if (!sel.value) { cell.textContent = ''; return; }
            var bad = opt && opt.getAttribute('data-unmatched') === '1';
            cell.className = 'small ' + (bad ? 'match-bad' : 'match-ok');
            cell.textContent = bad ? '✗ Not in your Class 12 subjects' : '✓ Matched';
        });
    }

    function counters(form) {
        form.querySelectorAll('textarea[data-count]').forEach(function (ta) {
            var out = document.createElement('div');
            out.className = 'form-text text-end';
            ta.insertAdjacentElement('afterend', out);
            function upd() { out.textContent = ta.value.length + ' / ' + ta.getAttribute('maxlength') + ' characters (minimum ' + ta.getAttribute('data-count') + ')'; }
            ta.addEventListener('input', upd); upd();
        });
    }

    function filePreview() {
        document.querySelectorAll('input[type=file][data-autosubmit]').forEach(function (inp) {
            inp.addEventListener('change', function () { if (inp.files.length) { inp.form.submit(); } });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-app-form]').forEach(function (form) {
            applyConditions(form);
            form.addEventListener('change', function () { applyConditions(form); cuetMatch(form); });
            cuetMatch(form);
            form.addEventListener('input', function () { recalc(form); });
            districts(form);
            repeaters(form);
            counters(form);
            recalc(form);
        });
        filePreview();
    });
})();
