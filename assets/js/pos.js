/* pos.js - order screen (staff POS + customer tracker) */
(function () {
    'use strict';
    var esc = SMS.esc, C = window.ORD, API = APP.base + '/orders/api', S = { o: null, items: [], bill: null, menu: [], cat: 'All', q: '', veg: false, pick: null };
    var panel = document.getElementById('orderPanel');
    function post(a, b) { return SMS.api(API, { method: 'POST', body: Object.assign({ action: a, id: C.id }, b || {}) }); }
    function apply(r) { if (r.order) { S.o = r.order; S.items = r.items; S.bill = r.bill; render(); } }
    function load() { return SMS.api(API + '?action=get&id=' + C.id).then(function (r) { if (r.success) apply(r); }); }
    function act(p) { return p.then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) apply(r); else load(); return r; }); }

    var ST = { Draft: 'secondary', New: 'info', Preparing: 'warning', Ready: 'success', Served: 'primary', Cancelled: 'danger' };
    function render() {
        var o = S.o, open = o.status === 'Open', cnt = { Draft: 0, New: 0, Preparing: 0, Ready: 0 }, sub = 0;
        S.items.forEach(function (i) { if (i.status !== 'Cancelled') { sub += i.unit_price * i.qty; } if (cnt[i.status] !== undefined) cnt[i.status] += +i.qty; });
        var h = '<div class="d-flex align-items-start mb-2"><div><div class="fw-bold fs-5">' + esc(o.order_no) + '</div><div class="small text-muted">' + esc(o.type) + (o.table_no ? ' · Table ' + esc(o.table_no) : '') + (o.type === 'Dine-in' ? ' · ' + o.guests + ' guests' : '') + '</div>' +
            '<div class="small text-muted">' + esc(o.customer_name || o.guest_name || 'Walk-in') + (o.waiter_name ? ' · ' + esc(o.waiter_name) : '') + '</div>' + (o.delivery_address ? '<div class="small"><i class="bi bi-geo-alt"></i> ' + esc(o.delivery_address) + '</div>' : '') + (o.note ? '<div class="small"><i class="bi bi-chat-left-text"></i> ' + esc(o.note) + '</div>' : '') + '</div><div class="ms-auto text-end">' + SMS.badge(o.status) + '</div></div>';
        if (!S.items.length) h += '<div class="text-center text-muted py-4">No items yet.</div>';
        S.items.forEach(function (i) {
            var cancelled = i.status === 'Cancelled';
            h += '<div class="line-item' + (cancelled ? ' opacity-50' : '') + '"><span class="fs-5">' + esc(i.icon || '') + '</span><div class="flex-grow-1"><div class="' + (cancelled ? 'text-decoration-line-through' : 'fw-semibold') + '">' + esc(i.name) + '</div>' + (i.note ? '<div class="small text-muted">' + esc(i.note) + '</div>' : '') +
                '<span class="badge badge-soft-' + ST[i.status] + '">' + i.status + (i.kot_no ? ' · KOT ' + i.kot_no : '') + '</span></div>';
            if (C.floor && open && i.status === 'Draft') h += '<div class="qty-ctl"><button data-q="-1" data-i="' + i.id + '" data-n="' + i.qty + '">−</button><span>' + i.qty + '</span><button data-q="1" data-i="' + i.id + '" data-n="' + i.qty + '">+</button></div>';
            else h += '<span class="text-muted">×' + i.qty + '</span>';
            h += '<div class="text-end" style="min-width:80px">' + SMS.money(i.unit_price * i.qty) + '</div>';
            if (C.floor && open && (i.status === 'Draft' || (C.role === 'admin' && ['New', 'Preparing', 'Ready', 'Served'].indexOf(i.status) >= 0))) h += '<button class="btn btn-sm btn-soft-danger ms-1" data-rm="' + i.id + '" title="Remove"><i class="bi bi-x"></i></button>';
            h += '</div>';
        });
        h += '<div class="receipt-row mt-2"><span>Subtotal</span><strong>' + SMS.money(sub) + '</strong></div>';
        if (S.bill) h += '<div class="receipt-row"><span>Bill ' + esc(S.bill.bill_no) + '</span><span>' + SMS.money(S.bill.total) + ' · ' + SMS.badge(S.bill.status) + '</span></div><a class="btn btn-gradient w-100 mt-2" href="' + APP.base + '/bills/' + S.bill.id + '"><i class="bi bi-receipt"></i> Open bill</a>';
        if (C.floor && open) {
            h += '<div class="d-grid gap-2 mt-3">';
            if (cnt.Draft) h += '<button class="btn btn-gradient" id="bSend"><i class="bi bi-send"></i> Send to kitchen (' + cnt.Draft + ')</button>';
            if (cnt.Ready) h += '<button class="btn btn-success" id="bServe"><i class="bi bi-check2-all"></i> Serve ready items (' + cnt.Ready + ')</button>';
            if (!cnt.Draft && !cnt.New && !cnt.Preparing && sub > 0) h += '<button class="btn btn-dark" id="bBill"><i class="bi bi-receipt"></i> Generate bill</button>';
            else if (sub > 0 && (cnt.New || cnt.Preparing || cnt.Draft)) h += '<div class="small text-muted text-center">Billing unlocks when nothing is unsent or in the kitchen.</div>';
            h += '<div class="d-flex gap-2">' + (o.type === 'Dine-in' ? '<button class="btn btn-soft flex-fill" id="bMove"><i class="bi bi-arrow-left-right"></i> Move</button>' : '') + '<button class="btn btn-soft-danger flex-fill" id="bCancel"><i class="bi bi-x-circle"></i> Cancel order</button></div></div>';
        }
        if (!C.floor) {
            var steps = ['New', 'Preparing', 'Ready', 'Served'], idx = 0; S.items.forEach(function (i) { var k = steps.indexOf(i.status); if (k >= 0) idx = idx === 0 ? k + 1 : Math.min(idx, k + 1); });
            if (open && sub > 0) h += '<div class="progress-steps mt-3">' + steps.map(function (s, k) { return '<div class="' + (k < idx ? 'done' : (k === idx ? 'now' : '')) + '"><i></i><span>' + s + '</span></div>'; }).join('') + '</div>';
            var allNew = S.items.every(function (i) { return i.status === 'New' || i.status === 'Cancelled'; });
            if (open && o.source === 'Online' && allNew) h += '<button class="btn btn-soft-danger w-100 mt-3" id="bCCancel">Cancel my order</button>';
        }
        panel.innerHTML = h;
    }
    function bind() {
        panel.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return;
            if (b.dataset.q) { var n = +b.dataset.n + +b.dataset.q; act(n < 1 ? post('remove_item', { item_id: b.dataset.i }) : post('set_qty', { item_id: b.dataset.i, qty: n })); }
            else if (b.dataset.rm) { SMS.confirmBox('Remove this item from the order?').then(function (ok) { if (ok) act(post('remove_item', { item_id: b.dataset.rm })); }); }
            else if (b.id === 'bSend') act(post('send'));
            else if (b.id === 'bServe') act(post('serve'));
            else if (b.id === 'bBill') openBill();
            else if (b.id === 'bMove') new bootstrap.Modal(document.getElementById('moveModal')).show();
            else if (b.id === 'bCancel') SMS.confirmBox('Cancel this entire order?').then(function (ok) { if (ok) act(post('cancel')); });
            else if (b.id === 'bCCancel') SMS.confirmBox('Cancel your order?').then(function (ok) { if (ok) act(post('customer_cancel')); });
        });
    }
    // ---------- menu
    function renderMenu() {
        var cats = ['All']; S.menu.forEach(function (m) { if (cats.indexOf(m.category) < 0) cats.push(m.category); });
        document.getElementById('catChips').innerHTML = cats.map(function (c) { return '<button class="btn btn-sm ' + (c === S.cat ? 'btn-gradient' : 'btn-soft') + '" data-c="' + esc(c) + '">' + esc(c) + '</button>'; }).join('');
        var q = S.q.toLowerCase(), list = S.menu.filter(function (m) { return (S.cat === 'All' || m.category === S.cat) && (!q || m.name.toLowerCase().indexOf(q) >= 0) && (!S.veg || +m.is_veg); });
        document.getElementById('menuGrid').innerHTML = list.map(function (m) { return '<div class="menu-card' + (+m.is_available ? '' : ' soldout') + '" data-m="' + m.id + '"><div class="mi">' + esc(m.icon || '🍽️') + '</div><div class="mn"><span class="veg-dot ' + (+m.is_veg ? '' : 'nv') + '"></span> ' + esc(m.name) + '</div><div class="mp">' + (+m.is_available ? SMS.money(m.price) : 'Sold out') + '</div></div>'; }).join('') || '<div class="text-muted">No dishes match.</div>';
    }
    function initMenu() {
        SMS.api(API + '?action=menu').then(function (r) { S.menu = r.data || []; renderMenu(); });
        document.getElementById('catChips').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { S.cat = b.dataset.c; renderMenu(); } });
        document.getElementById('mSearch').addEventListener('input', function () { S.q = this.value; renderMenu(); });
        document.getElementById('vegOnly').addEventListener('change', function () { S.veg = this.checked; renderMenu(); });
        var nm = new bootstrap.Modal(document.getElementById('noteModal'));
        document.getElementById('menuGrid').addEventListener('click', function (e) {
            var c = e.target.closest('.menu-card'); if (!c || c.classList.contains('soldout')) return;
            if (S.o.status !== 'Open') { SMS.toast('This order is ' + S.o.status.toLowerCase() + '.', 'warning'); return; }
            S.pick = S.menu.filter(function (m) { return +m.id === +c.dataset.m; })[0]; document.getElementById('nmName').textContent = S.pick.name; document.getElementById('nmQty').value = 1; document.getElementById('nmNote').value = ''; nm.show();
        });
        document.getElementById('nmAdd').addEventListener('click', function () {
            var q = parseInt(document.getElementById('nmQty').value, 10); if (!(q >= 1 && q <= 50)) { SMS.toast('Quantity must be 1–50.', 'danger'); return; }
            act(post('add_item', { menu_item_id: S.pick.id, qty: q, note: document.getElementById('nmNote').value })).then(function (r) { if (r.success) nm.hide(); });
        });
        document.getElementById('mvGo').addEventListener('click', function () { act(post('move', { table_id: document.getElementById('mvTable').value })).then(function (r) { if (r.success) bootstrap.Modal.getInstance(document.getElementById('moveModal')).hide(); }); });
    }
    // ---------- billing
    var bm, bt;
    function quote() {
        var q = 'discount_value=' + encodeURIComponent(document.getElementById('bdVal').value || 0) + '&discount_type=' + document.getElementById('bdType').value + '&points=' + encodeURIComponent(document.getElementById('bdPts').value || 0);
        SMS.api(API + '?action=quote&id=' + C.id + '&' + q).then(function (r) {
            var x = r.quote; document.getElementById('ptsAvail').textContent = '(available: ' + r.points_available + ')';
            document.getElementById('quoteBox').innerHTML = (r.message ? '<div class="alert alert-danger py-2 small">' + esc(r.message) + '</div>' : '') +
                [['Subtotal', x.subtotal], ['Discount', -x.discount], ['Points', -x.points_value], ['Service charge (' + C.svc + '%)', x.service], ['VAT (' + C.tax + '% on base + service)', x.tax]].map(function (p) { return '<div class="receipt-row"><span>' + p[0] + '</span><span>' + SMS.money(p[1]) + '</span></div>'; }).join('') + '<div class="receipt-total"><span>Total</span><span>' + SMS.money(x.total) + '</span></div>';
            document.getElementById('bdGo').disabled = !!r.message;
        });
    }
    function openBill() {
        bm = bm || new bootstrap.Modal(document.getElementById('billModal'));
        var adm = C.role === 'admin' || C.role === 'cashier'; document.getElementById('discRow').style.display = adm ? '' : 'none';
        document.getElementById('ptsRow').style.display = S.o.customer_id ? '' : 'none';
        document.getElementById('bdVal').value = 0; document.getElementById('bdPts').value = 0; bm.show(); quote();
    }
    function initBill() {
        ['bdVal', 'bdType', 'bdPts'].forEach(function (id) { document.getElementById(id).addEventListener('input', function () { clearTimeout(bt); bt = setTimeout(quote, 250); }); });
        document.getElementById('bdGo').addEventListener('click', function () {
            SMS.api(API, { method: 'POST', body: { action: 'generate', id: C.id, discount_value: document.getElementById('bdVal').value, discount_type: document.getElementById('bdType').value, points: document.getElementById('bdPts').value } }).then(function (r) {
                SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { bm.hide(); location.href = APP.base + '/bills/' + r.bill_id; } });
        });
    }
    bind(); if (C.floor) { initMenu(); initBill(); }
    load(); setInterval(function () { if (!document.hidden && !document.querySelector('.modal.show')) load(); }, 10000);
})();
