/* =========================================================
   crud.js - generic DataTable + modal form for simple modules
   new Crud({ table, api, label, columns, fields, canWrite, ... })
   ========================================================= */
(function () {
    'use strict';
    var esc = SMS.esc;

    function Crud(cfg) {
        var self = this;
        this.cfg = cfg;
        this.id = 0;
        this.uniqueOk = {};
        var $tbl = $(cfg.table);

        /* ---------- table ---------- */
        var cols = cfg.columns.slice();
        if (cfg.canWrite || cfg.actions) {
            cols.push({
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (r) {
                    var h = cfg.actions ? cfg.actions(r) : '';
                    if (cfg.canWrite) {
                        h += ' <button class="btn btn-icon btn-soft-primary" title="Edit" data-act="edit"><i class="bi bi-pencil-square"></i></button>';
                        if (cfg.canDelete !== false) h += ' <button class="btn btn-icon btn-soft-danger" title="Delete" data-act="del"><i class="bi bi-trash3"></i></button>';
                    }
                    return h;
                }
            });
        }
        this.table = $tbl.DataTable({
            ajax: { url: cfg.api + '?action=list', dataSrc: 'data' },
            columns: cols, order: cfg.order || [[0, 'asc']], pageLength: cfg.pageLength || 10,
            language: { search: '', searchPlaceholder: 'Search…', emptyTable: 'Nothing here yet.' },
            createdRow: cfg.createdRow || null,
            drawCallback: function () { if (cfg.onDraw) cfg.onDraw(self); }
        });

        $tbl.on('click', 'button[data-act]', function () {
            var act = this.dataset.act, row = self.table.row($(this).closest('tr')).data();
            if (cfg.onAction && cfg.onAction(act, row, self, this) === true) return;
            if (act === 'edit') self.edit(row);
            if (act === 'del') self.remove(row);
        });

        if (!cfg.fields || !cfg.canWrite) return;

        /* ---------- modal form ---------- */
        this.buildModal();
        var add = document.getElementById(cfg.addButton || 'btnAdd');
        if (add) add.addEventListener('click', function () { self.open(null); });
    }

    Crud.prototype.rowName = function (r) { return this.cfg.rowName ? this.cfg.rowName(r) : (r.name || r.reg_no || r.sku || r.full_name || ('#' + r.id)); };
    Crud.prototype.reload = function () { this.table.ajax.reload(null, false); };

    Crud.prototype.buildModal = function () {
        var self = this, cfg = this.cfg;
        var fields = cfg.fields.map(function (f) { return self.fieldHtml(f); }).join('');
        var wrap = document.createElement('div');
        wrap.className = 'modal fade';
        wrap.tabIndex = -1;
        wrap.innerHTML =
            '<div class="modal-dialog ' + (cfg.modalSize || 'modal-lg') + ' modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" novalidate autocomplete="off">' +
            '<div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body"><div class="row g-3">' + fields + '</div></div>' +
            '<div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="submit" class="btn btn-gradient"><i class="bi bi-check2-circle me-1"></i> Save</button></div></form></div>';
        document.body.appendChild(wrap);
        this.modalEl = wrap; this.form = wrap.querySelector('form'); this.modal = new bootstrap.Modal(wrap);
        this.form.addEventListener('submit', function (e) { e.preventDefault(); self.submit(); });

        // live unique check
        cfg.fields.forEach(function (f) {
            if (!f.unique) return;
            var inp = self.form.elements[f.name];
            var check = SMS.debounce(function () { self.checkUnique(f, inp); }, 350);
            inp.addEventListener('input', function () { self.uniqueOk[f.name] = false; check(); });
        });
    };

    Crud.prototype.fieldHtml = function (f) {
        var col = f.col || 6, id = 'f_' + f.name;
        var req = f.required ? ' <span class="text-danger">*</span>' : '';
        var cls = 'col-md-' + col + (f.createOnly ? ' only-create' : '') + (f.editOnly ? ' only-edit' : '');
        var attrs = ' id="' + id + '" name="' + f.name + '"' + (f.required ? ' required' : '') + (f.max ? ' maxlength="' + f.max + '"' : '') + (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '');
        var input;
        if (f.type === 'select') {
            input = '<select class="form-select"' + attrs + '><option value="">' + esc(f.placeholder || '— Select —') + '</option>' +
                (f.options || []).map(function (o) { return '<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>'; }).join('') + '</select>';
        } else if (f.type === 'textarea') {
            input = '<textarea class="form-control" rows="2"' + attrs + '></textarea>';
        } else if (f.type === 'checkbox') {
            return '<div class="' + cls + '"><div class="form-check form-switch mt-4 pt-1"><input class="form-check-input" type="checkbox" id="' + id + '" name="' + f.name + '" value="1"' + (f.default ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="' + id + '">' + esc(f.label) + '</label></div>' + (f.help ? '<div class="form-text">' + esc(f.help) + '</div>' : '') + '</div>';
        } else {
            var t = f.type || 'text';
            input = '<input class="form-control' + (f.upper ? ' text-uppercase' : '') + '" type="' + t + '"' + attrs +
                (f.min !== undefined ? ' min="' + f.min + '"' : '') + (f.maxv !== undefined ? ' max="' + f.maxv + '"' : '') + (f.step ? ' step="' + f.step + '"' : '') + '>';
        }
        return '<div class="' + cls + '"><label class="form-label" for="' + id + '">' + esc(f.label) + req + '</label>' +
            '<div class="position-relative">' + input + '<span class="uniq-ind"></span></div><div class="invalid-feedback" data-for="' + f.name + '"></div>' +
            '<div class="valid-feedback">Available.</div>' + (f.help ? '<div class="form-text">' + esc(f.help) + '</div>' : '') + '</div>';
    };

    Crud.prototype.setError = function (name, msg) {
        var inp = this.form.elements[name]; if (!inp) return;
        inp.classList.add('is-invalid'); inp.classList.remove('is-valid');
        var fb = this.form.querySelector('.invalid-feedback[data-for="' + name + '"]'); if (fb) fb.textContent = msg;
    };
    Crud.prototype.clearErrors = function () {
        this.form.querySelectorAll('.is-invalid,.is-valid').forEach(function (e) { e.classList.remove('is-invalid', 'is-valid'); });
        this.form.querySelectorAll('.uniq-ind').forEach(function (e) { e.innerHTML = ''; });
    };

    Crud.prototype.open = function (row) {
        var self = this, cfg = this.cfg;
        this.form.reset(); this.clearErrors(); this.uniqueOk = {};
        this.id = row ? row.id : 0;
        this.modalEl.querySelector('.modal-title').textContent = (row ? 'Edit ' : 'Add ') + cfg.label;
        this.modalEl.querySelectorAll('.only-create').forEach(function (e) { e.style.display = row ? 'none' : ''; });
        this.modalEl.querySelectorAll('.only-edit').forEach(function (e) { e.style.display = row ? '' : 'none'; });
        cfg.fields.forEach(function (f) {
            var el = self.form.elements[f.name]; if (!el) return;
            var v = row ? row[f.name] : (f.default !== undefined ? f.default : '');
            if (f.type === 'checkbox') el.checked = row ? !!parseInt(row[f.name], 10) : !!f.default;
            else el.value = (v === null || v === undefined) ? '' : v;
            if (f.placeholderEdit) el.placeholder = row ? f.placeholderEdit : (f.placeholder || '');
        });
        if (cfg.onOpen) cfg.onOpen(row, this);
        this.modal.show();
    };
    Crud.prototype.edit = function (row) {
        var self = this;
        SMS.api(this.cfg.api + '?action=get&id=' + row.id).then(function (res) {
            if (!res.success) { SMS.toast(res.message, 'danger'); return; }
            self.open(res.data);
        });
    };

    Crud.prototype.checkUnique = function (f, inp) {
        var self = this, v = inp.value.trim(), ind = inp.parentNode.querySelector('.uniq-ind');
        if (v === '') { ind.innerHTML = ''; return; }
        if (self.localInvalid(f, v)) { ind.innerHTML = ''; return; }
        ind.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary"></span>';
        SMS.api(this.cfg.api + '?action=check&field=' + encodeURIComponent(f.name) + '&value=' + encodeURIComponent(v) + '&exclude_id=' + this.id).then(function (res) {
            if (inp.value.trim() !== v) return;
            if (res.success && res.exists) { self.uniqueOk[f.name] = false; self.setError(f.name, 'This ' + f.label.toLowerCase() + ' is already in use.'); ind.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>'; }
            else if (res.success) { self.uniqueOk[f.name] = true; inp.classList.remove('is-invalid'); inp.classList.add('is-valid'); ind.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>'; }
            else ind.innerHTML = '';
        });
    };

    /** returns an error string or '' */
    Crud.prototype.localInvalid = function (f, v) {
        if (f.type === 'checkbox') return '';
        v = (v || '').trim();
        if (v === '') return f.required && !(this.id && f.placeholderEdit) ? f.label + ' is required.' : '';
        if (f.pattern && !new RegExp(f.pattern, f.upper ? 'i' : '').test(v)) return f.patternMsg || (f.label + ' is not in a valid format.');
        if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Enter a valid email address.';
        if (f.type === 'number') {
            var n = Number(v);
            if (!isFinite(n)) return f.label + ' must be a number.';
            if (f.min !== undefined && n < f.min) return f.label + ' must be at least ' + f.min + '.';
            if (f.maxv !== undefined && n > f.maxv) return f.label + ' must be at most ' + f.maxv + '.';
            if (f.integer && Math.floor(n) !== n) return f.label + ' must be a whole number.';
        }
        if (f.minLen && v.length < f.minLen) return f.label + ' must be at least ' + f.minLen + ' characters.';
        return '';
    };

    Crud.prototype.validate = function () {
        var self = this, ok = true;
        this.cfg.fields.forEach(function (f) {
            var el = self.form.elements[f.name];
            if (!el || (f.createOnly && self.id) || (f.editOnly && !self.id)) return;
            var msg = self.localInvalid(f, f.type === 'checkbox' ? '' : el.value);
            if (msg) { self.setError(f.name, msg); ok = false; }
            else if (f.unique && el.classList.contains('is-invalid')) ok = false;
            else el.classList.remove('is-invalid');
        });
        return ok;
    };

    Crud.prototype.submit = function () {
        var self = this;
        if (!this.validate()) { SMS.toast('Please correct the highlighted fields.', 'warning'); return; }
        var btn = this.form.querySelector('[type=submit]'), label = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
        var fd = new FormData(this.form);
        fd.append('action', 'save'); fd.append('id', this.id);
        SMS.api(this.cfg.api, { method: 'POST', body: fd }).then(function (res) {
            btn.disabled = false; btn.innerHTML = label;
            if (res.success) {
                self.modal.hide(); SMS.toast(res.message, 'success'); self.reload();
                if (self.cfg.onSaved) self.cfg.onSaved(res);
            } else {
                if (res.errors) Object.keys(res.errors).forEach(function (k) { self.setError(k, res.errors[k]); });
                SMS.toast(res.message || 'Could not save.', 'danger');
            }
        });
    };

    Crud.prototype.remove = function (row) {
        var self = this;
        SMS.confirmBox('Delete "' + this.rowName(row) + '"? This cannot be undone.', 'Delete').then(function (yes) {
            if (!yes) return;
            var fd = new FormData(); fd.append('action', 'delete'); fd.append('id', row.id);
            SMS.api(self.cfg.api, { method: 'POST', body: fd }).then(function (res) {
                SMS.toast(res.message, res.success ? 'success' : 'danger');
                if (res.success) self.reload();
            });
        });
    };

    window.Crud = Crud;
})();
