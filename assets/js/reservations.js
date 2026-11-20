(function () {
    'use strict';
    var esc = SMS.esc, R = window.RES, API = APP.base + '/reservations/api', f = R.customer ? 'all' : 'today', dt, rows = [], seatId = 0;
    function range() { var t = R.today; if (f === 'today') return 'from=' + t + '&to=' + t; if (f === 'upcoming') { var d = new Date(t + 'T00:00:00'); d.setDate(d.getDate() + 1); return 'from=' + d.toISOString().slice(0, 10); } if (f === 'past') { var p = new Date(t + 'T00:00:00'); p.setDate(p.getDate() - 1); return 'to=' + p.toISOString().slice(0, 10); } return ''; }
    function load() { SMS.api(API + '?action=list&' + range()).then(function (r) { rows = r.data || []; dt.clear().rows.add(rows).draw(); }); }
    dt = $('#tbl').DataTable({ data: [], order: [], pageLength: 15, language: { emptyTable: 'No reservations.' },
        columns: [{ data: 'res_no', render: function (d) { return '<span class="plate">' + esc(d) + '</span>'; } },
            { data: 'guest_name', render: function (d, t, r) { return '<div class="fw-semibold">' + esc(d) + '</div><div class="small text-muted">' + esc(r.phone) + '</div>' + (r.notes ? '<div class="small text-muted">' + esc(r.notes) + '</div>' : ''); } },
            { data: 'res_date', render: function (d, t, r) { return t === 'display' ? SMS.fdate(d) + ' <strong>' + esc(String(r.res_time).slice(0, 5)) + '</strong>' : d + r.res_time; } },
            { data: 'party_size', className: 'text-center' }, { data: 'table_no', render: function (d, t, r) { return d ? esc(d) + ' <span class="small text-muted">' + esc(r.area) + '</span>' : '–'; } },
            { data: 'status', render: function (d) { return SMS.badge(d); } },
            { data: null, orderable: false, className: 'text-end', render: function (d, t, r) {
                if (r.status !== 'Booked') return ''; var h = '';
                if (!R.customer && r.res_date === R.today) h += '<button class="btn btn-sm btn-gradient me-1" data-a="seat" data-id="' + r.id + '">Seat</button><button class="btn btn-sm btn-soft me-1" data-a="no_show" data-id="' + r.id + '">No-show</button>';
                return h + '<button class="btn btn-sm btn-soft-danger" data-a="cancel" data-id="' + r.id + '">Cancel</button>'; } }] });
    function post(b) { return SMS.api(API, { method: 'POST', body: b }); }
    var sm = new bootstrap.Modal(document.getElementById('seatModal'));
    document.getElementById('tbl').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-a]'); if (!b) return; var id = +b.dataset.id, a = b.dataset.a;
        if (a === 'seat') { var r = rows.filter(function (x) { return +x.id === id; })[0]; seatId = id; document.getElementById('stInfo').textContent = r.guest_name + ' · party of ' + r.party_size;
            SMS.api(API + '?action=tables&date=' + r.res_date + '&time=' + String(r.res_time).slice(0, 5) + '&party=' + r.party_size).then(function (x) { var t = x.data || []; if (!t.some(function (q) { return +q.id === +r.table_id; }) && r.table_id) t.unshift({ id: r.table_id, table_no: r.table_no, area: r.area, seats: r.seats });
                document.getElementById('stTable').innerHTML = t.map(function (q) { return '<option value="' + q.id + '"' + (+q.id === +r.table_id ? ' selected' : '') + '>' + esc(q.table_no) + ' · ' + esc(q.area) + ' · ' + q.seats + ' seats</option>'; }).join(''); sm.show(); }); return; }
        SMS.confirmBox(a === 'cancel' ? 'Cancel this reservation?' : 'Mark as no-show?').then(function (ok) { if (ok) post({ action: a, id: id }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }); });
    });
    document.getElementById('stGo').addEventListener('click', function () { post({ action: 'seat', id: seatId, table_id: document.getElementById('stTable').value }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) location.href = APP.base + '/orders/' + r.order_id; else load(); }); });
    var rf = document.getElementById('rF'); if (rf) rf.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); f = b.dataset.f; load(); });

    // booking
    var bm = new bootstrap.Modal(document.getElementById('bkModal')), form = document.getElementById('bkForm'), slotsBox = document.getElementById('bkSlots');
    var fp = flatpickr('#bkDate', { minDate: R.today, maxDate: R.max, dateFormat: 'Y-m-d', defaultDate: R.today, onChange: slots });
    document.getElementById('bkParty').addEventListener('input', slots);
    function slots() {
        var d = document.getElementById('bkDate').value, p = parseInt(document.getElementById('bkParty').value, 10); document.getElementById('bkTime').value = '';
        if (!d || !(p >= 1 && p <= 30)) { slotsBox.innerHTML = '<span class="text-muted small">Pick a date and party size.</span>'; return; }
        SMS.api(API + '?action=slots&date=' + d + '&party=' + p).then(function (r) {
            if (!r.success) { slotsBox.innerHTML = '<span class="text-danger small">' + esc(r.message) + '</span>'; return; }
            slotsBox.innerHTML = (r.data.length ? r.data.map(function (s) { return '<button type="button" class="slot-pill' + (s.free ? '' : ' full') + '" data-t="' + s.time + '"' + (s.free ? '' : ' disabled') + '>' + s.time + '</button>'; }).join('') : '<span class="text-muted small">No slots left today.</span>'); });
    }
    slotsBox.addEventListener('click', function (e) { var b = e.target.closest('.slot-pill'); if (!b || b.disabled) return; slotsBox.querySelectorAll('.slot-pill').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); document.getElementById('bkTime').value = b.dataset.t; });
    document.getElementById('btnBook').addEventListener('click', function () { form.reset(); fp.setDate(R.today); document.getElementById('bkParty').value = 2; slots(); bm.show(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); var b = { action: 'book' }; new FormData(form).forEach(function (v, k) { b[k] = v; });
        if (!b.res_time) { SMS.toast('Pick a time slot.', 'danger'); return; }
        post(b).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { bm.hide(); load(); } else slots(); }); });
    if (!R.customer) {
        var inp = document.getElementById('bkCust'), res = document.getElementById('bkRes');
        inp.addEventListener('input', SMS.debounce(function () { var q = inp.value.trim(); document.getElementById('bkCid').value = ''; if (q.length < 2) { res.innerHTML = ''; return; }
            SMS.api(APP.base + '/customers/api?action=search&q=' + encodeURIComponent(q)).then(function (r) { res.innerHTML = (r.data || []).map(function (c) { return '<button type="button" class="list-group-item list-group-item-action" data-id="' + c.id + '" data-n="' + esc(c.full_name) + '" data-p="' + esc(c.phone) + '">' + esc(c.full_name) + ' · ' + esc(c.phone) + '</button>'; }).join(''); }); }, 250));
        res.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; document.getElementById('bkCid').value = b.dataset.id; document.getElementById('bkName').value = b.dataset.n; document.getElementById('bkPhone').value = b.dataset.p; inp.value = b.dataset.n; res.innerHTML = ''; });
    }
    load();
})();
