(function () {
    'use strict';
    var esc = SMS.esc, st = '', dt;
    function load() { SMS.api(APP.base + '/bills/api?action=list&status=' + st).then(function (r) { dt.clear().rows.add(r.data || []).draw(); }); }
    dt = $('#tbl').DataTable({ data: [], order: [], pageLength: 15, language: { emptyTable: 'No bills.' },
        columns: [{ data: 'bill_no', render: function (d, t, r) { return '<a class="fw-semibold" href="' + APP.base + '/bills/' + r.id + '"><span class="plate">' + esc(d) + '</span></a>'; } },
            { data: 'order_no' }, { data: 'customer_name', render: function (d) { return esc(d); } }, { data: 'created_at', render: function (d, t) { return t === 'display' ? SMS.fdatetime(d) : d; } },
            { data: 'total', className: 'text-end', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } }, { data: 'paid', className: 'text-end', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } },
            { data: 'status', render: function (d) { return SMS.badge(d); } }] });
    document.getElementById('bF').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); st = b.dataset.s; load(); });
    load();
})();
