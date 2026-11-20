(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/order-online/api', menu = [], cart = {}, cat = 'All', q = '', veg = false, type = 'Takeaway';
    function find(id) { return menu.filter(function (m) { return +m.id === +id; })[0]; }
    function renderMenu() {
        var cats = ['All']; menu.forEach(function (m) { if (cats.indexOf(m.category) < 0) cats.push(m.category); });
        document.getElementById('catChips').innerHTML = cats.map(function (c) { return '<button class="btn btn-sm ' + (c === cat ? 'btn-gradient' : 'btn-soft') + '" data-c="' + esc(c) + '">' + esc(c) + '</button>'; }).join('');
        var s = q.toLowerCase(), list = menu.filter(function (m) { return (cat === 'All' || m.category === cat) && (!s || m.name.toLowerCase().indexOf(s) >= 0) && (!veg || +m.is_veg); });
        document.getElementById('menuGrid').innerHTML = list.map(function (m) { return '<div class="menu-card' + (+m.is_available ? '' : ' soldout') + '" data-m="' + m.id + '"><div class="mi">' + esc(m.icon || '🍽️') + '</div><div class="mn"><span class="veg-dot ' + (+m.is_veg ? '' : 'nv') + '"></span> ' + esc(m.name) + '</div><div class="mp">' + (+m.is_available ? SMS.money(m.price) : 'Sold out') + '</div></div>'; }).join('');
    }
    function renderCart() {
        var ids = Object.keys(cart), t = 0; document.getElementById('cartLines').innerHTML = ids.length ? ids.map(function (id) { var m = find(id); t += m.price * cart[id];
            return '<div class="line-item"><div class="flex-grow-1 fw-semibold">' + esc(m.name) + '</div><div class="qty-ctl"><button data-d="-1" data-id="' + id + '">−</button><span>' + cart[id] + '</span><button data-d="1" data-id="' + id + '">+</button></div><div class="text-end" style="min-width:70px">' + SMS.money(m.price * cart[id]) + '</div></div>'; }).join('') : '<div class="text-muted small">Your cart is empty.</div>';
        document.getElementById('cTotal').textContent = ids.length ? SMS.money(t) : '–'; document.getElementById('cGo').disabled = !ids.length;
    }
    document.getElementById('catChips').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { cat = b.dataset.c; renderMenu(); } });
    document.getElementById('mSearch').addEventListener('input', function () { q = this.value; renderMenu(); });
    document.getElementById('vegOnly').addEventListener('change', function () { veg = this.checked; renderMenu(); });
    document.getElementById('menuGrid').addEventListener('click', function (e) { var c = e.target.closest('.menu-card'); if (!c || c.classList.contains('soldout')) return; var id = c.dataset.m; cart[id] = Math.min(20, (cart[id] || 0) + 1); renderCart(); });
    document.getElementById('cartLines').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; var id = b.dataset.id; cart[id] += +b.dataset.d; if (cart[id] < 1) delete cart[id]; if (cart[id] > 20) cart[id] = 20; renderCart(); });
    document.getElementById('typeSel').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; type = b.dataset.t; this.querySelectorAll('button').forEach(function (x) { x.className = 'btn btn-sm ' + (x === b ? 'btn-gradient' : 'btn-soft'); }); document.getElementById('addrBox').style.display = type === 'Delivery' ? '' : 'none'; });
    document.getElementById('cGo').addEventListener('click', function () { var b = this; b.disabled = true;
        SMS.api(API, { method: 'POST', body: { type: type, lines: cart, delivery_address: document.getElementById('cAddr').value, note: document.getElementById('cNote').value } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) location.href = APP.base + '/orders/' + r.id; else b.disabled = false; }); });
    SMS.api(API).then(function (r) { menu = r.data || []; renderMenu(); });
})();
