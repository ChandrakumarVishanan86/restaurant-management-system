(function () {
    'use strict';
    var esc = SMS.esc, st = '', dt;
    function load() { SMS.api(APP.base + '/orders/api?action=list&status=' + encodeURIComponent(st)).then(function (r) { dt.clear().rows.add(r.data || []).draw(); }); }
    dt = $('#tbl').DataTable({ data: [], order: [], pageLength: 15, language: { emptyTable: 'No orders found.' },
        columns: [{ data: 'order_no', render: function (d, t, r) { return '<a class="fw-semibold" href="' + APP.base + '/orders/' + r.id + '"><span class="plate">' + esc(d) + '</span></a>' + (r.source === 'Online' ? ' <span class="badge badge-soft-info">Online</span>' : ''); } },
            { data: 'type' }, { data: 'table_no', render: function (d) { return d ? esc(d) : '–'; } }, { data: 'customer_name', render: function (d) { return esc(d); } },
            { data: 'item_count', className: 'text-center', render: function (d, t, r) { return d + (+r.ready_count ? ' <span class="badge bg-success">' + r.ready_count + ' ready</span>' : ''); } },
            { data: 'subtotal', className: 'text-end', render: function (d, t, r) { var v = r.bill_total !== null ? r.bill_total : d; return t === 'display' ? SMS.money(v) : v; } },
            { data: 'created_at', render: function (d, t) { return t === 'display' ? SMS.fdatetime(d) : d; } }, { data: 'status', render: function (d) { return SMS.badge(d); } }] });
    document.getElementById('stChips').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); st = b.dataset.s; load(); });
    load();
})();
