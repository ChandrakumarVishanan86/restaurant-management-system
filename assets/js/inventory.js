/* inventory.js - ingredient CRUD + receive / wastage / adjust / history */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/inventory/api', I = window.INV, cur = null, mode = '';
    function n(v) { return parseFloat(v).toLocaleString('en-US', { maximumFractionDigits: 3 }); }
    var crud = new Crud({ table: '#tbl', api: API, label: 'Ingredient', canWrite: true, order: [[1, 'asc']], rowName: function (r) { return r.name; },
        columns: [{ data: 'name', render: function (d, t, r) { return '<span class="fw-semibold">' + esc(d) + '</span>' + (parseInt(r.is_active, 10) ? '' : ' <span class="badge badge-soft-secondary">Inactive</span>') + '<div class="small text-muted">used in ' + r.used_in + ' dish(es)</div>'; } },
            { data: 'stock', render: function (d, t, r) { if (t !== 'display') return d; var pct = r.reorder_level > 0 ? Math.min(100, Math.round(d / (r.reorder_level * 3) * 100)) : 100, low = parseInt(r.low, 10);
                return '<span class="fw-bold ' + (low ? 'text-danger' : '') + '">' + n(d) + ' ' + r.unit + '</span>' + (low ? ' <span class="badge bg-danger">LOW</span>' : '') + '<div class="bar-sm"><span style="width:' + pct + '%;background:' + (low ? '#ef4444' : '#10b981') + '"></span></div>'; } },
            { data: 'reorder_level', className: 'text-end', render: function (d, t, r) { return n(d) + ' ' + r.unit; } }, { data: 'cost_per_unit', className: 'text-end', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } },
            { data: null, className: 'text-end', render: function (d, t, r) { var v = r.stock * r.cost_per_unit; return t === 'display' ? SMS.money(v) : v; } }, { data: 'supplier', render: function (d) { return d ? esc(d) : '–'; } }],
        actions: function () { return '<button class="btn btn-icon btn-soft-success" title="Receive stock" data-act="recv"><i class="bi bi-box-arrow-in-down"></i></button> <button class="btn btn-icon btn-soft-danger" title="Wastage" data-act="waste"><i class="bi bi-trash2"></i></button> <button class="btn btn-icon btn-soft" title="Stock-take adjustment" data-act="adj"><i class="bi bi-sliders"></i></button> <button class="btn btn-icon btn-soft" title="History" data-act="hist"><i class="bi bi-clock-history"></i></button>'; },
        onAction: function (act, row) { if (act === 'recv' || act === 'waste' || act === 'adj') { openStock(act, row); return true; } if (act === 'hist') { openHist(row); return true; } },
        fields: [{ name: 'name', label: 'Ingredient', required: true, max: 80, col: 8, unique: true }, { name: 'unit', label: 'Unit', type: 'select', required: true, col: 4, options: [['kg', 'kg'], ['l', 'litre'], ['pcs', 'pieces']] },
            { name: 'reorder_level', label: 'Reorder level', type: 'number', required: true, min: 0, step: '0.001', col: 4 }, { name: 'cost_per_unit', label: 'Cost per unit', type: 'number', required: true, min: 0, step: '0.01', col: 4 },
            { name: 'supplier_id', label: 'Supplier', type: 'select', col: 4, options: I.suppliers }, { name: 'opening_stock', label: 'Opening stock', type: 'number', min: 0, step: '0.001', col: 6, createOnly: true },
            { name: 'is_active', label: 'Active', type: 'checkbox', default: true, col: 6 }] });

    var modal = new bootstrap.Modal(document.getElementById('stockModal')), form = document.getElementById('stockForm'), fb = document.getElementById('stFields');
    function openStock(act, r) {
        cur = r; mode = act; var titles = { recv: 'Receive stock', waste: 'Record wastage', adj: 'Stock-take adjustment' };
        document.getElementById('stTitle').textContent = titles[act] + ' — ' + r.name; document.getElementById('stInfo').textContent = 'Current stock: ' + n(r.stock) + ' ' + r.unit;
        if (act === 'recv') fb.innerHTML = '<div class="col-6"><label class="form-label">Quantity (' + r.unit + ')</label><input type="number" class="form-control" name="qty" min="0.001" step="0.001" required></div><div class="col-6"><label class="form-label">Cost per ' + r.unit + '</label><input type="number" class="form-control" name="unit_cost" min="0" step="0.01" value="' + r.cost_per_unit + '"></div>' +
            '<div class="col-6"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">— Default —</option>' + I.suppliers.map(function (s) { return '<option value="' + s[0] + '"' + (+s[0] === +r.supplier_id ? ' selected' : '') + '>' + esc(s[1]) + '</option>'; }).join('') + '</select></div><div class="col-6"><label class="form-label">Reference</label><input class="form-control" name="reference" maxlength="60" placeholder="GRN / invoice no"></div>';
        if (act === 'waste') fb.innerHTML = '<div class="col-6"><label class="form-label">Quantity (' + r.unit + ')</label><input type="number" class="form-control" name="qty" min="0.001" max="' + r.stock + '" step="0.001" required></div><div class="col-12"><label class="form-label">Reason</label><input class="form-control" name="reference" maxlength="60" placeholder="Spoiled, dropped, expired…" required></div>';
        if (act === 'adj') fb.innerHTML = '<div class="col-6"><label class="form-label">Counted stock (' + r.unit + ')</label><input type="number" class="form-control" name="new_stock" min="0" step="0.001" required></div><div class="col-12"><label class="form-label">Reason</label><input class="form-control" name="reference" maxlength="60" placeholder="Monthly stock-take" required></div>';
        modal.show();
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); var body = { action: mode === 'recv' ? 'receive' : (mode === 'waste' ? 'wastage' : 'adjust'), id: cur.id };
        new FormData(form).forEach(function (v, k) { body[k] = v; });
        SMS.api(API, { method: 'POST', body: body }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { modal.hide(); setTimeout(function () { location.reload(); }, 500); } }); });

    var hm = new bootstrap.Modal(document.getElementById('histModal'));
    function openHist(r) { document.getElementById('hiTitle').textContent = 'Stock history — ' + r.name; var b = document.getElementById('hiBody'); b.innerHTML = 'Loading…'; hm.show();
        SMS.api(API + '?action=movements&id=' + r.id).then(function (res) { var T = { Purchase: 'success', Usage: 'secondary', Wastage: 'danger', Adjustment: 'warning' };
            b.innerHTML = '<table class="table table-sm align-middle"><thead><tr><th>When</th><th>Type</th><th class="text-end">Qty</th><th>Reference</th><th>By</th></tr></thead><tbody>' + (res.data || []).map(function (m) {
                return '<tr><td class="small">' + SMS.fdatetime(m.created_at) + '</td><td><span class="badge badge-soft-' + T[m.type] + '">' + m.type + '</span></td><td class="text-end fw-semibold ' + (m.qty < 0 ? 'text-danger' : 'text-success') + '">' + (m.qty > 0 ? '+' : '') + n(m.qty) + ' ' + r.unit + '</td><td class="small">' + esc(m.reference || '') + (m.supplier ? ' · ' + esc(m.supplier) : '') + '</td><td class="small">' + esc(m.user_name || '—') + '</td></tr>'; }).join('') + '</tbody></table>'; }); }
})();
