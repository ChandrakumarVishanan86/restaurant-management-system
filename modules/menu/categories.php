<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Menu Categories'; $activeNav = 'menu'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Menu categories</h5><small class="text-muted">The station decides where items print on the kitchen display. <a href="<?= url('menu') ?>">Back to menu</a></small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add Category</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Category</th><th>Station</th><th class="text-center">Items</th><th class="text-center">Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
<script>
new Crud({ table: '#tbl', api: APP.base + '/menu/categories-api', label: 'Category', canWrite: true, modalSize: 'modal-md', order: [[3, 'asc']],
    columns: [{ data: 'name', render: function (d) { return '<span class="fw-semibold">' + SMS.esc(d) + '</span>'; } }, { data: 'station' }, { data: 'items', className: 'text-center' }, { data: 'sort_order', className: 'text-center' },
        { data: 'is_active', render: function (d) { return parseInt(d, 10) ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Hidden</span>'; } }],
    fields: [{ name: 'name', label: 'Category name', required: true, max: 60, col: 12, unique: true }, { name: 'station', label: 'Station', type: 'select', required: true, col: 6, options: [['Kitchen', 'Kitchen'], ['Bar', 'Bar']] },
        { name: 'sort_order', label: 'Sort order', type: 'number', min: 0, maxv: 999, integer: true, default: 0, col: 6 }, { name: 'is_active', label: 'Active', type: 'checkbox', default: true, col: 12 }] });
</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
