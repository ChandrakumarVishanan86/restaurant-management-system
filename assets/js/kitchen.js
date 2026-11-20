(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/kitchen/api', K = window.KDS, cook = K.role === 'admin' || K.role === 'chef', station = '', data = [], t0 = Date.now(), base = 0;
    function mm(s) { s = Math.max(0, s); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
    function render() {
        var el = document.getElementById('kds'), el2 = (Date.now() - t0) / 1000;
        var cards = data.map(function (g) {
            var items = g.items.filter(function (i) { return !station || i.station === station; }); if (!items.length) return '';
            var worst = 'age-ok', age = 0, pairs = [];
            items.forEach(function (i) { var a = i.age_sec + el2, p = i.prep_min * 60; if (i.status !== 'Ready') { pairs.push([i.age_sec, i.prep_min * 60]); age = Math.max(age, a); if (a > p * 1.5) worst = 'age-late'; else if (a > p && worst !== 'age-late') worst = 'age-warn'; } });
            var nw = items.filter(function (i) { return i.status === 'New'; }), pr = items.filter(function (i) { return i.status === 'Preparing'; }), rd = items.filter(function (i) { return i.status === 'Ready'; });
            var h = '<div class="kot ' + worst + '" data-p="' + esc(JSON.stringify(pairs)) + '"><div class="kot-head"><strong>' + (g.table_no ? 'T' + esc(g.table_no) : esc(g.type)) + '</strong><span class="small opacity-75">' + esc(g.order_no) + ' · KOT ' + g.kot_no + '</span><span class="t">' + mm(Math.round(age)) + '</span></div>';
            if (g.source === 'Online' || g.order_note) h += '<div class="px-3 pt-2 small">' + (g.source === 'Online' ? '<span class="badge badge-soft-info">Online</span> ' : '') + esc(g.customer || '') + (g.order_note ? ' — <em>' + esc(g.order_note) + '</em>' : '') + '</div>';
            items.forEach(function (i) { h += '<div class="kot-row"><span class="q">' + i.qty + '×</span><div class="flex-grow-1"><div class="fw-semibold">' + esc(i.icon || '') + ' ' + esc(i.name) + (i.station === 'Bar' ? ' <span class="badge badge-soft-secondary">Bar</span>' : '') + '</div>' + (i.note ? '<div class="small text-danger">' + esc(i.note) + '</div>' : '') + '</div><span class="badge badge-soft-' + ({ New: 'info', Preparing: 'warning', Ready: 'success' })[i.status] + '">' + i.status + '</span></div>'; });
            h += '<div class="kot-foot">';
            if (cook && nw.length) h += '<button class="btn btn-sm btn-warning flex-fill" data-to="Preparing" data-ids="' + nw.map(function (i) { return i.id; }) + '"><i class="bi bi-fire"></i> Start ' + nw.length + '</button>';
            if (cook && pr.length) h += '<button class="btn btn-sm btn-success flex-fill" data-to="Ready" data-ids="' + pr.map(function (i) { return i.id; }) + '"><i class="bi bi-check2"></i> Ready ' + pr.length + '</button>';
            if (!cook && rd.length) h += '<button class="btn btn-sm btn-success flex-fill" data-to="Served" data-ids="' + rd.map(function (i) { return i.id; }) + '"><i class="bi bi-check2-all"></i> Served ' + rd.length + '</button>';
            if (cook && rd.length && !nw.length && !pr.length) h += '<span class="small text-success flex-fill text-center">Waiting for the waiter to serve</span>';
            return h + '</div></div>';
        }).join('');
        el.innerHTML = cards || '<div class="card-panel text-center text-muted py-5" style="grid-column:1/-1"><i class="bi bi-emoji-smile fs-1"></i><div>No pending tickets. 🎉</div></div>';
    }
    function load() { SMS.api(API).then(function (r) { if (!r.success) return; data = r.data; t0 = Date.now(); var s = r.stats;
        document.getElementById('kStats').innerHTML = '<span><strong>' + s.waiting + '</strong> waiting</span><span><strong>' + s.cooking + '</strong> cooking</span><span><strong>' + s.ready + '</strong> ready</span><span><strong>' + (s.avg_prep || '–') + '</strong> min avg prep</span>';
        document.getElementById('kNow').textContent = r.now; render(); }); }
    document.getElementById('kds').addEventListener('click', function (e) { var b = e.target.closest('button[data-to]'); if (!b) return; b.disabled = true;
        SMS.api(API, { method: 'POST', body: { action: 'advance', to: b.dataset.to, ids: b.dataset.ids.split(',') } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }); });
    document.getElementById('stationF').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); station = b.dataset.s; render(); });
    load(); setInterval(load, 8000); setInterval(tick, 1000);
    function tick() { var el2 = (Date.now() - t0) / 1000;
        document.querySelectorAll('#kds .kot').forEach(function (k) { var pr = []; try { pr = JSON.parse(k.dataset.p || '[]'); } catch (e) {} var age = 0, w = 'age-ok';
            pr.forEach(function (x) { var a = x[0] + el2; age = Math.max(age, a); if (a > x[1] * 1.5) w = 'age-late'; else if (a > x[1] && w !== 'age-late') w = 'age-warn'; });
            k.classList.remove('age-ok', 'age-warn', 'age-late'); k.classList.add(w); var t = k.querySelector('.kot-head .t'); if (t) t.textContent = mm(Math.round(age)); }); }
})();
