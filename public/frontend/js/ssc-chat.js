// Helpdesk chat: polls for new messages and appends them (text only, never HTML).
(function () {
    var box = document.querySelector('[data-chat-feed]');
    if (!box) return;
    var feed = box.getAttribute('data-chat-feed');
    var after = parseInt(box.getAttribute('data-after'), 10) || 0;
    var seconds = parseInt(box.getAttribute('data-interval'), 10) || 8;

    function add(m) {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg ' + (m.mine ? 'mine' : 'theirs');
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = m.sender + ' · ' + m.time;
        var body = document.createElement('div');
        body.className = 'chat-body';
        body.textContent = m.body;
        wrap.appendChild(meta);
        wrap.appendChild(body);
        if (m.attachment) {
            var a = document.createElement('a');
            a.href = m.attachment; a.target = '_blank'; a.rel = 'noopener';
            a.textContent = 'Attachment';
            a.className = 'chat-att';
            wrap.appendChild(a);
        }
        box.appendChild(wrap);
        box.scrollTop = box.scrollHeight;
    }

    function poll() {
        if (document.visibilityState !== 'visible') return;
        fetch(feed + (feed.indexOf('?') > -1 ? '&' : '?') + 'after=' + after, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { messages: [] }; })
            .then(function (d) {
                (d.messages || []).forEach(function (m) { if (m.id > after) { add(m); after = m.id; } });
            })
            .catch(function () {});
    }

    box.scrollTop = box.scrollHeight;
    setInterval(poll, seconds * 1000);

    // Quick replies (staff page): insert text into the reply box.
    document.querySelectorAll('[data-quick-reply]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ta = document.getElementById(btn.getAttribute('data-target'));
            if (ta) { ta.value = btn.getAttribute('data-quick-reply'); ta.focus(); }
        });
    });
})();
