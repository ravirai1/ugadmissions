// Reloads a queue page every N seconds so new paid applications show up, unless the user is typing.
(function () {
    var el = document.querySelector('[data-autorefresh]');
    if (!el) return;
    var seconds = parseInt(el.getAttribute('data-autorefresh'), 10) || 60;
    setInterval(function () {
        var active = document.activeElement;
        if (active && /INPUT|SELECT|TEXTAREA/.test(active.tagName)) return;
        if (document.visibilityState === 'visible') window.location.reload();
    }, seconds * 1000);
})();
