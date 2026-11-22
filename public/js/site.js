// Menu, reading progress, scroll reveal, lightbox and click-to-load YouTube. No dependencies.
(function () {
    document.documentElement.classList.add('js');

    var header = document.querySelector('[data-header]');
    var toggle = document.querySelector('[data-menu-toggle]');
    var nav = document.getElementById('hoofdmenu');
    var progress = document.querySelector('[data-progress]');
    var toTop = document.querySelector('[data-to-top]');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', String(open));
            nav.classList.toggle('is-open', open);
            header.classList.toggle('menu-open', open);
        });
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                toggle.setAttribute('aria-expanded', 'false');
                nav.classList.remove('is-open');
                header.classList.remove('menu-open');
            }
        });
    }

    function onScroll() {
        var y = window.scrollY;
        var max = document.documentElement.scrollHeight - window.innerHeight;
        if (header) header.classList.toggle('is-scrolled', y > 40);
        if (progress) progress.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%';
        if (toTop) toTop.classList.toggle('is-visible', y > window.innerHeight);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Reveal blocks and highlight the current chapter in the menu.
    if ('IntersectionObserver' in window) {
        var reveal = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    reveal.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px' });
        document.querySelectorAll('.reveal').forEach(function (el) { reveal.observe(el); });

        var links = {};
        document.querySelectorAll('[data-chapter-link]').forEach(function (a) { links[a.getAttribute('data-chapter-link')] = a; });
        var chapters = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && links[entry.target.id]) {
                    Object.keys(links).forEach(function (key) { links[key].classList.remove('is-active'); });
                    links[entry.target.id].classList.add('is-active');
                }
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        document.querySelectorAll('[data-chapter][id]').forEach(function (el) { chapters.observe(el); });
    } else {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
    }

    // YouTube: the player (and YouTube's cookies) only loads after a click.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-youtube]');
        if (!button) return;
        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(button.getAttribute('data-youtube')) + '?autoplay=1&rel=0';
        iframe.title = button.getAttribute('aria-label') || 'Video';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        // nginx sends "Referrer-Policy: same-origin"; YouTube refuses to play without a referrer (error 153).
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        button.replaceWith(iframe);
    });

    // Lightbox for gallery photos and single images.
    var box = document.querySelector('[data-lightbox]');
    if (!box) return;
    var img = box.querySelector('[data-lightbox-img]');
    var caption = box.querySelector('[data-lightbox-caption]');
    var items = [];
    var index = 0;
    var lastFocus = null;

    function show(i) {
        index = (i + items.length) % items.length;
        img.src = items[index].href;
        img.alt = items[index].getAttribute('data-caption') || '';
        caption.textContent = items[index].getAttribute('data-caption') || '';
        box.querySelector('[data-lightbox-prev]').hidden = items.length < 2;
        box.querySelector('[data-lightbox-next]').hidden = items.length < 2;
    }
    function close() {
        box.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus) lastFocus.focus();
    }
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-lightbox-item]');
        if (!link) return;
        event.preventDefault();
        var group = link.closest('[data-gallery]');
        items = Array.prototype.slice.call((group || document).querySelectorAll('[data-lightbox-item]'));
        lastFocus = link;
        show(items.indexOf(link));
        box.hidden = false;
        document.body.style.overflow = 'hidden';
        box.querySelector('[data-lightbox-close]').focus();
    });
    box.querySelector('[data-lightbox-close]').addEventListener('click', close);
    box.querySelector('[data-lightbox-prev]').addEventListener('click', function () { show(index - 1); });
    box.querySelector('[data-lightbox-next]').addEventListener('click', function () { show(index + 1); });
    box.addEventListener('click', function (event) { if (event.target === box) close(); });
    document.addEventListener('keydown', function (event) {
        if (box.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') show(index - 1);
        if (event.key === 'ArrowRight') show(index + 1);
    });
})();
