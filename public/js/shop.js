// Ordering at the table: cart in localStorage, checkout sheet, posts JSON to the server.
(function () {
    var S = window.SHOP;
    var products = {};
    S.menu.products.forEach(function (p) { products[p.id] = p; });
    var cart = {};
    try { cart = JSON.parse(localStorage.getItem('vti-cart') || '{}') || {}; } catch (e) { cart = {}; }

    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var bar = document.querySelector('[data-cart-bar]');
    var sheet = document.querySelector('[data-sheet]');
    var form = document.querySelector('[data-checkout]');
    var errors = document.querySelector('[data-errors]');

    function money(n) {
        var text = (Math.round(n * 100) / 100).toLocaleString('nl-BE', { maximumFractionDigits: 2 });
        return text + ' ' + (n === 1 ? S.singular : S.plural);
    }
    function maxFor(p) { return p.max === null ? S.maxQuantity : Math.min(S.maxQuantity, p.max); }

    function clean() {
        Object.keys(cart).forEach(function (id) {
            var p = products[id];
            if (!p || !p.available || cart[id] < 1) delete cart[id];
            else cart[id] = Math.min(cart[id], maxFor(p));
        });
    }
    function save() { try { localStorage.setItem('vti-cart', JSON.stringify(cart)); } catch (e) {} }

    function change(id, delta) {
        var p = products[id];
        if (!p || !p.available) return;
        var next = (cart[id] || 0) + delta;
        if (next > maxFor(p)) next = maxFor(p);
        if (next <= 0) delete cart[id]; else cart[id] = next;
        save();
        render();
    }

    function totals() {
        var count = 0, total = 0;
        Object.keys(cart).forEach(function (id) { count += cart[id]; total += cart[id] * products[id].price; });
        return { count: count, total: total };
    }

    function render() {
        document.querySelectorAll('[data-product]').forEach(function (el) {
            var q = cart[el.getAttribute('data-product')] || 0;
            var qty = el.querySelector('[data-qty]');
            var minus = el.querySelector('[data-minus]');
            if (!qty) return;
            qty.textContent = q;
            qty.hidden = minus.hidden = q === 0;
            el.classList.toggle('in-cart', q > 0);
        });
        var t = totals();
        bar.hidden = t.count === 0;
        document.querySelector('[data-cart-count]').textContent = t.count;
        document.querySelector('[data-cart-total]').textContent = money(t.total);
        document.querySelector('[data-sheet-total]').textContent = money(t.total);

        var lines = document.querySelector('[data-lines]');
        lines.innerHTML = '';
        Object.keys(cart).forEach(function (id) {
            var p = products[id];
            var li = document.createElement('li');
            li.innerHTML = '<button type="button" class="qty-btn" aria-label="minder">&minus;</button><span class="qty"></span><button type="button" class="qty-btn qty-plus" aria-label="meer">+</button><span class="line-name"></span><span class="line-price"></span>';
            li.querySelector('.qty').textContent = cart[id];
            li.querySelector('.line-name').textContent = p.name;
            li.querySelector('.line-price').textContent = money(cart[id] * p.price);
            li.children[0].addEventListener('click', function () { change(id, -1); if (!totals().count) closeSheet(); });
            li.children[2].addEventListener('click', function () { change(id, 1); });
            lines.appendChild(li);
        });
    }

    document.addEventListener('click', function (e) {
        var plus = e.target.closest('[data-plus]');
        var minus = e.target.closest('[data-minus]');
        if (plus) change(plus.closest('[data-product]').getAttribute('data-product'), 1);
        if (minus) change(minus.closest('[data-product]').getAttribute('data-product'), -1);
        if (e.target.closest('[data-open-checkout]')) openSheet();
        if (e.target.closest('[data-close-checkout]')) closeSheet();
    });

    function openSheet() { sheet.hidden = false; document.body.style.overflow = 'hidden'; }
    function closeSheet() { sheet.hidden = true; document.body.style.overflow = ''; }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });

    // Remember the name for the next round.
    var nameInput = form.querySelector('[data-remember="name"]');
    try { if (nameInput && !nameInput.value) nameInput.value = localStorage.getItem('vti-name') || ''; } catch (e) {}

    // The bonnen instructions only make sense with bonnen.
    function toggleHelp() {
        var method = form.querySelector('input[name="payment_method"]:checked');
        var help = form.querySelector('[data-bonnen-help]');
        if (help) help.hidden = !method || method.value !== 'bonnen';
    }
    form.addEventListener('change', toggleHelp);
    toggleHelp();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var button = form.querySelector('[data-submit]');
        var data = {
            table: (form.elements.table || {}).value || '',
            name: nameInput ? nameInput.value : '',
            note: form.elements.note ? form.elements.note.value : '',
            payment_method: (form.querySelector('input[name="payment_method"]:checked') || {}).value || 'bonnen',
            items: cart,
        };
        try { localStorage.setItem('vti-name', data.name); } catch (err) {}
        errors.hidden = true;
        button.disabled = true;
        button.textContent = 'Even geduld...';

        fetch(S.storeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(data),
        }).then(function (response) {
            return response.json().then(function (json) { return { ok: response.ok, status: response.status, json: json }; });
        }).then(function (res) {
            if (res.ok && res.json.redirect) {
                window.location.href = res.json.redirect;
                return;
            }
            var messages = [];
            if (res.status === 419) messages.push('De pagina was te lang open. Vernieuw de pagina en probeer opnieuw (je bestelling blijft bewaard).');
            Object.values((res.json && res.json.errors) || {}).forEach(function (list) { messages = messages.concat(list); });
            if (!messages.length) messages.push((res.json && res.json.message) || 'Er ging iets mis. Probeer opnieuw.');
            showErrors(messages);
            if (res.status === 422) refreshMenu();
        }).catch(function () {
            showErrors(['Geen verbinding. Controleer je internet en probeer opnieuw.']);
        }).finally(function () {
            button.disabled = false;
            button.textContent = 'Bestelling plaatsen';
        });
    });

    function showErrors(list) {
        errors.innerHTML = '<ul></ul>';
        list.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; errors.firstChild.appendChild(li); });
        errors.hidden = false;
        errors.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Something sold out meanwhile: reload the menu in the background, keep the cart.
    function refreshMenu() {
        fetch(location.href, { headers: { 'Accept': 'text/html' } }).then(function (r) { return r.text(); }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var fresh = doc.querySelector('.shop-menu');
            if (fresh) document.querySelector('.shop-menu').replaceWith(fresh);
            var script = Array.prototype.find.call(doc.scripts, function (s) { return s.textContent.indexOf('window.SHOP') !== -1; });
            if (script) {
                (new Function(script.textContent))();
                window.SHOP.menu.products.forEach(function (p) { products[p.id] = p; });
            }
            clean(); save(); render();
        }).catch(function () {});
    }

    // Highlight the category in view. Only the category bar scrolls sideways; never the page itself
    // (scrollIntoView would also move the page vertically and fight the user's scrolling).
    var catNav = document.querySelector('[data-cats]');
    var links = document.querySelectorAll('[data-cats] a');
    var activeId = null;
    function setActive(id) {
        if (id === activeId) return;
        activeId = id;
        links.forEach(function (a) {
            var on = a.getAttribute('href') === '#' + id;
            a.classList.toggle('is-active', on);
            if (on && catNav) {
                var left = a.offsetLeft - (catNav.clientWidth - a.offsetWidth) / 2;
                catNav.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
            }
        });
    }
    if (links.length && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) { if (entry.isIntersecting) setActive(entry.target.id); });
        }, { rootMargin: '-35% 0px -60% 0px' });
        document.querySelectorAll('[data-cat-section][id]').forEach(function (s) { io.observe(s); });
    }

    // The category bar sticks right under the header, whatever the header's real height is.
    var header = document.querySelector('.shop-header');
    function measure() {
        if (header) document.documentElement.style.setProperty('--header-h', header.offsetHeight + 'px');
    }
    measure();
    window.addEventListener('resize', measure, { passive: true });

    clean(); save(); render();
})();
