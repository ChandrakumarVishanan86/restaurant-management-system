/* menu.js - menu CRUD, availability toggle, recipe editor */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/menu/api', M = window.MENU, ings = [], curId = 0;
    var crud = new Crud({ table: '#tbl', api: API, label: 'Menu item', canWrite: true, order: [[1, 'asc']], rowName: function (r) { return r.name; },
        columns: [
            { data: 'name', render: function (d, t, r) { return '<span class="fs-5 me-1">' + esc(r.icon || '') + '</span><span class="fw-semibold">' + esc(d) + '</span> <span class="veg-dot ' + (parseInt(r.is_veg, 10) ? '' : 'nv') + '"></span>' + (parseInt(r.is_active, 10) ? '' : ' <span class="badge badge-soft-secondary">Hidden</span>'); } },
            { data: 'category' }, { data: 'price', className: 'text-end', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } },
            { data: 'cost', className: 'text-end', render: function (d, t, r) { if (t !== 'display') return d; var m = r.price > 0 ? Math.round((1 - d / r.price) * 100) : 0; return parseInt(r.ingredients, 10) ? SMS.money(d) + '<div class="small ' + (m < 40 ? 'text-danger' : 'text-success') + '">' + m + '% margin</div>' : '<span class="text-muted small">no recipe</span>'; } },
            { data: 'prep_minutes', className: 'text-center', render: function (d) { return d + ' min'; } }, { data: 'sold', className: 'text-center' },
            { data: 'is_available', render: function (d) { return parseInt(d, 10) ? '<button class="btn btn-sm badge-soft-success border-0" data-act="avail" data-v="0">Available</button>' : '<button class="btn btn-sm badge-soft-danger border-0" data-act="avail" data-v="1">Sold out</button>'; } }],
        actions: function () { return '<button class="btn btn-icon btn-soft" title="Recipe" data-act="recipe"><i class="bi bi-list-check"></i></button>'; },
        onAction: function (act, row, self, el) {
            if (act === 'avail') { SMS.api(API, { method: 'POST', body: { action: 'toggle', id: row.id, available: el.dataset.v } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); self.reload(); }); return true; }
            if (act === 'recipe') { openRecipe(row); return true; }
        },
        fields: [{ name: 'name', label: 'Item name', required: true, max: 100, col: 8 }, { name: 'icon', label: 'Emoji', max: 8, col: 4, placeholder: '🍛' },
            { name: 'category_id', label: 'Category', type: 'select', required: true, col: 6, options: M.cats }, { name: 'price', label: 'Price', type: 'number', required: true, min: 1, maxv: 999999, step: '0.01', col: 3 },
            { name: 'prep_minutes', label: 'Prep (min)', type: 'number', required: true, min: 1, maxv: 180, integer: true, col: 3, default: 15 },
            { name: 'description', label: 'Description', max: 255, col: 12 },
            { name: 'spice_level', label: 'Spice', type: 'select', col: 4, options: [['0', 'Mild'], ['1', 'Medium'], ['2', 'Hot'], ['3', 'Extra hot']] },
            { name: 'is_veg', label: 'Vegetarian', type: 'checkbox', col: 2 }, { name: 'is_available', label: 'Available today', type: 'checkbox', default: true, col: 3 }, { name: 'is_active', label: 'On menu', type: 'checkbox', default: true, col: 3 }] });

    var modal = new bootstrap.Modal(document.getElementById('recipeModal')), box = document.getElementById('rcLines');
    function row(l) {
        var d = document.createElement('div'); d.className = 'd-flex gap-2 mb-2 align-items-center';
        d.innerHTML = '<select class="form-select form-select-sm" data-i><option value="">— Ingredient —</option>' + ings.map(function (i) { return '<option value="' + i.id + '" data-u="' + i.unit + '" data-c="' + i.cost_per_unit + '"' + (l && +l.ingredient_id === +i.id ? ' selected' : '') + '>' + esc(i.name) + ' (' + i.unit + ')</option>'; }).join('') + '</select>' +
            '<input type="number" class="form-control form-control-sm" style="width:110px" data-q min="0.001" step="0.001" value="' + (l ? l.qty : '') + '" placeholder="Qty"><span class="small text-muted" style="width:32px" data-u></span><button type="button" class="btn btn-sm btn-soft-danger" data-x><i class="bi bi-x"></i></button>';
        box.appendChild(d); unit(d);
    }
    function unit(d) { var o = d.querySelector('[data-i]').selectedOptions[0]; d.querySelector('[data-u]').textContent = o && o.dataset.u ? o.dataset.u : ''; }
    function summary() { var t = 0; box.querySelectorAll('.d-flex').forEach(function (d) { var o = d.querySelector('[data-i]').selectedOptions[0]; t += (parseFloat(o && o.dataset.c) || 0) * (parseFloat(d.querySelector('[data-q]').value) || 0); });
        var p = curRow ? +curRow.price : 0; document.getElementById('rcSummary').innerHTML = 'Food cost per portion: <strong>' + SMS.money(t) + '</strong>' + (p ? ' · selling price ' + SMS.money(p) + ' · margin <strong>' + Math.round((1 - t / p) * 100) + '%</strong>' : ''); }
    var curRow = null;
    function openRecipe(r) { curRow = r; curId = r.id; document.getElementById('rcTitle').textContent = 'Recipe — ' + r.name; box.innerHTML = '';
        SMS.api(API + '?action=recipe&id=' + r.id).then(function (res) { ings = res.ingredients || []; (res.data || []).forEach(row); if (!res.data.length) row(); summary(); modal.show(); }); }
    document.getElementById('rcAdd').addEventListener('click', function () { row(); });
    box.addEventListener('click', function (e) { var x = e.target.closest('[data-x]'); if (x) { x.parentNode.remove(); summary(); } });
    box.addEventListener('change', function (e) { var d = e.target.closest('.d-flex'); if (d) unit(d); summary(); }); box.addEventListener('input', summary);
    document.getElementById('rcSave').addEventListener('click', function () {
        var lines = []; box.querySelectorAll('.d-flex').forEach(function (d) { var i = d.querySelector('[data-i]').value, q = d.querySelector('[data-q]').value; if (i || q) lines.push({ ingredient_id: i, qty: q }); });
        SMS.api(API, { method: 'POST', body: { action: 'save_recipe', id: curId, lines: lines } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { modal.hide(); crud.reload(); } });
    });
})();
