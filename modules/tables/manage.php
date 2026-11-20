<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Manage Tables'; $activeNav = 'tables'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Dining tables</h5><small class="text-muted">Add or edit tables. <a href="<?= url('tables') ?>">Back to floor plan</a></small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add Table</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Table</th><th>Area</th><th class="text-center">Seats</th><th>Status</th><th>Active</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
<script>
new Crud({ table: '#tbl', api: APP.base + '/tables/api', label: 'Table', canWrite: true, rowName: function (r) { return 'Table ' + r.table_no; }, modalSize: 'modal-md',
    columns: [{ data: 'table_no', render: function (d) { return '<span class="plate">' + SMS.esc(d) + '</span>'; } }, { data: 'area' }, { data: 'seats', className: 'text-center' },
        { data: 'status', render: function (d) { return SMS.badge(d); } }, { data: 'is_active', render: function (d) { return parseInt(d, 10) ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
    fields: [{ name: 'table_no', label: 'Table number', required: true, max: 10, col: 6, unique: true, upper: true, pattern: '^[A-Za-z0-9\\-]{1,10}$', patternMsg: 'Letters, numbers or - only.' },
        { name: 'area', label: 'Area', type: 'select', required: true, col: 6, options: [['Indoor', 'Indoor'], ['Outdoor', 'Outdoor'], ['Rooftop', 'Rooftop'], ['Private', 'Private']] },
        { name: 'seats', label: 'Seats', type: 'number', required: true, min: 1, maxv: 30, integer: true, col: 6 }, { name: 'is_active', label: 'Active', type: 'checkbox', default: true, col: 6 }] });
</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
