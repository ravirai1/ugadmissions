/* Prospectus flipbook: pdf.js renders each page to an image, StPageFlip turns them. */
(function () {
    var wrap = document.getElementById('flipbook-wrap');
    if (!wrap || !window.pdfjsLib || !window.St) return;
    var status = document.getElementById('flipbook-status');
    var book = document.getElementById('flipbook');
    var bar = document.getElementById('flipbook-bar');
    var label = document.getElementById('fb-page');
    pdfjsLib.GlobalWorkerOptions.workerSrc = wrap.dataset.worker;

    function fail() {
        status.innerHTML = '';
        status.textContent = 'The prospectus could not be shown here. Please use the Download PDF button.';
    }

    pdfjsLib.getDocument({ url: wrap.dataset.pdf, isEvalSupported: false }).promise.then(function (pdf) {
        return pdf.getPage(1).then(function (first) {
            var vp = first.getViewport({ scale: 1 });
            var ratio = vp.height / vp.width;
            var avail = wrap.clientWidth - 32;
            var single = avail < 760;
            // fit one page on screen: limited by the width available and by the window height
            var maxH = Math.max(360, window.innerHeight - 170);
            var w = Math.floor(Math.min(single ? avail : avail / 2, 560, maxH / ratio));
            var h = Math.floor(w * ratio);
            var scale = (w * Math.min(window.devicePixelRatio || 1, 2)) / vp.width;
            var pages = [];
            var chain = Promise.resolve();
            for (var i = 1; i <= pdf.numPages; i++) {
                (function (n) {
                    chain = chain.then(function () { return pdf.getPage(n); }).then(function (page) {
                        var v = page.getViewport({ scale: scale });
                        var c = document.createElement('canvas');
                        c.width = v.width; c.height = v.height;
                        return page.render({ canvasContext: c.getContext('2d'), viewport: v }).promise.then(function () {
                            var div = document.createElement('div');
                            div.className = 'page';
                            if (n === 1 || n === pdf.numPages) div.setAttribute('data-density', 'hard');
                            var img = document.createElement('img');
                            img.alt = 'Page ' + n;
                            img.src = c.toDataURL('image/jpeg', 0.85);
                            div.appendChild(img);
                            pages.push(div);
                            status.textContent = 'Preparing pages… ' + n + ' of ' + pdf.numPages;
                        });
                    });
                })(i);
            }
            return chain.then(function () {
                pages.forEach(function (p) { book.appendChild(p); });
                status.remove();
                var flip = new St.PageFlip(book, {
                    width: w, height: h, size: 'fixed', showCover: true, usePortrait: single,
                    maxShadowOpacity: 0.5, mobileScrollSupport: true
                });
                flip.loadFromHTML(book.querySelectorAll('.page'));
                var total = flip.getPageCount();
                var show = function () { label.textContent = 'Page ' + (flip.getCurrentPageIndex() + 1) + ' of ' + total; };
                flip.on('flip', show); show();
                bar.classList.remove('d-none');
                document.getElementById('fb-prev').addEventListener('click', function () { flip.flipPrev(); });
                document.getElementById('fb-next').addEventListener('click', function () { flip.flipNext(); });
                document.getElementById('fb-first').addEventListener('click', function () { flip.turnToPage(0); show(); });
                document.getElementById('fb-last').addEventListener('click', function () { flip.turnToPage(total - 1); show(); });
                document.addEventListener('keydown', function (e) {
                    if (e.target.closest('input, textarea, select')) return;
                    if (e.key === 'ArrowLeft') flip.flipPrev();
                    if (e.key === 'ArrowRight') flip.flipNext();
                });
            });
        });
    }).catch(fail);
})();
