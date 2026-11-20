/* tables.js - live floor plan + open order */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/tables/api', host = document.getElementById('floor');
    var modal = new bootstrap.Modal(document.getElementById('openModal')), form = document.getElementById('openForm'), cur = null;
    var COLORS = { Available: 'success', Occupied: 'danger', Cleaning: 'warning', Reserved: 'primary' };

    function mins(from, now) { return Math.max(0, Math.round((new Date(now.replace(' ', 'T')) - new Date(from.replace(' ', 'T'))) / 60000)); }
    function card(t, now) {
        var c = COLORS[t.status] || 'secondary', busy = t.status === 'Occupied' && t.order_id;
        var h = '<div class="table-card tc-' + c + '" data-id="' + t.id + '" tabindex="0" role="button"><div class="d-flex align-items-start"><span class="tc-no">' + esc(t.table_no) + '</span><span class="ms-auto small text-muted"><i class="bi bi-people"></i> ' + t.seats + '</span></div>';
        if (busy) {
            h += '<div class="tc-meta"><div class="fw-semibold">' + SMS.money(t.running) + '</div><div class="small text-muted">' + t.guests + ' guests · ' + mins(t.opened_at, now) + ' min' + (t.waiter ? ' · ' + esc(t.waiter.split(' ')[0]) : '') + '</div>';
            if (parseInt(t.ready_items, 10) > 0) h += '<span class="badge bg-success mt-1"><i class="bi bi-bell-fill"></i> ' + t.ready_items + ' ready to serve</span>';
            if (t.order_status === 'Billed') h += '<span class="badge bg-warning text-dark mt-1">Billed</span>';
            h += '</div>';
        } else h += '<div class="tc-meta small text-muted">' + (t.status === 'Cleaning' ? 'Needs cleaning' : t.status) + '</div>';
        if (t.next_res) h += '<div class="tc-res"><i class="bi bi-calendar2-heart"></i> ' + esc(t.next_res) + '</div>';
        return h + '</div>';
    }
    function render(res) {
        var groups = {}; res.data.forEach(function (t) { (groups[t.area] = groups[t.area] || []).push(t); });
        window.__tables = {}; res.data.forEach(function (t) { window.__tables[t.id] = t; });
        host.innerHTML = Object.keys(groups).map(function (a) {
            return '<h6 class="fw-bold text-muted text-uppercase small mt-3 mb-2">' + esc(a) + '</h6><div class="table-grid">' + groups[a].map(function (t) { return card(t, res.now); }).join('') + '</div>'; }).join('');
    }
    function load() { SMS.api(API + '?action=floor').then(function (r) { if (r.success) render(r); }); }
    load(); setInterval(load, 15000);

    host.addEventListener('click', function (e) { var el = e.target.closest('.table-card'); if (el) pick(window.__tables[el.dataset.id]); });
    host.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var el = e.target.closest('.table-card'); if (el) pick(window.__tables[el.dataset.id]); } });
    function pick(t) {
        if (t.order_id) { location.href = APP.base + '/orders/' + t.order_id; return; }
        if (t.status === 'Cleaning') { SMS.confirmBox('Table ' + t.table_no + ' is clean and ready for guests?', 'Mark available').then(function (ok) { if (!ok) return;
            SMS.api(API, { method: 'POST', body: { action: 'set_status', id: t.id, status: 'Available' } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }); }); return; }
        openFor(t);
    }
    function openFor(t) {
        cur = t; form.reset(); document.getElementById('oCid').value = ''; document.getElementById('oCustPicked').textContent = '';
        document.getElementById('openTitle').textContent = t ? 'Open table ' + t.table_no + ' (' + t.seats + ' seats)' : 'Takeaway / Delivery order';
        document.getElementById('typeSel').style.display = t ? 'none' : ''; document.getElementById('guestsCol').style.display = t ? '' : 'none';
        var w = document.getElementById('waiterCol'); if (w) w.style.display = t ? '' : 'none';
        document.querySelectorAll('.other-only').forEach(function (e) { e.style.display = t ? 'none' : ''; }); document.getElementById('addrCol').style.display = 'none';
        if (t) document.getElementById('oGuests').max = t.seats; modal.show();
    }
    document.getElementById('btnOther').addEventListener('click', function () { openFor(null); });
    document.querySelectorAll('input[name=otype]').forEach(function (r) { r.addEventListener('change', function () { document.getElementById('addrCol').style.display = r.value === 'Delivery' && r.checked ? '' : 'none'; }); });

    var s = document.getElementById('oCust'), l = document.getElementById('oCustList');
    var run = SMS.debounce(function () { var q = s.value.trim(); if (q.length < 2) { l.style.display = 'none'; return; }
        SMS.api(APP.base + '/customers/api?action=search&q=' + encodeURIComponent(q)).then(function (r) { l.innerHTML = (r.data || []).map(function (c) { return '<button type="button" class="list-group-item list-group-item-action" data-id="' + c.id + '" data-name="' + esc(c.full_name) + '" data-addr="' + esc(c.address || '') + '" data-pts="' + c.loyalty_points + '"><strong>' + esc(c.full_name) + '</strong> <small class="text-muted">' + esc(c.code) + ' · ' + esc(c.phone) + '</small></button>'; }).join('') || '<div class="list-group-item text-muted">No customers found.</div>'; l.style.display = 'block'; }); }, 250);
    s.addEventListener('input', function () { document.getElementById('oCid').value = ''; document.getElementById('oCustPicked').textContent = ''; run(); });
    l.addEventListener('click', function (e) { var b = e.target.closest('[data-id]'); if (!b) return; document.getElementById('oCid').value = b.dataset.id; s.value = b.dataset.name; l.style.display = 'none';
        document.getElementById('oCustPicked').textContent = b.dataset.pts + ' loyalty points'; if (!document.getElementById('oAddr').value) document.getElementById('oAddr').value = b.dataset.addr; });

    form.addEventListener('submit', function (e) { e.preventDefault();
        var body = { action: 'open', customer_id: document.getElementById('oCid').value, note: document.getElementById('oNote').value };
        var w = document.getElementById('oWaiter'); if (w) body.waiter_id = w.value;
        if (cur) { body.type = 'Dine-in'; body.table_id = cur.id; body.guests = document.getElementById('oGuests').value; }
        else { body.type = document.querySelector('input[name=otype]:checked').value; body.guest_name = document.getElementById('oName').value; body.guest_phone = document.getElementById('oPhone').value; body.delivery_address = document.getElementById('oAddr').value; }
        SMS.api(APP.base + '/orders/api', { method: 'POST', body: body }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) location.href = APP.base + '/orders/' + r.id; else load(); });
    });
})();
