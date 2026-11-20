<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin', 'chef']);
$pageTitle = 'Suppliers'; $activeNav = 'suppliers'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Suppliers</h5><small class="text-muted">Used when receiving ingredient stock.</small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add Supplier</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Supplier</th><th>Phone</th><th>Email</th><th class="text-center">Ingredients</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
<script>
new Crud({ table: '#tbl', api: APP.base + '/suppliers/api', label: 'Supplier', canWrite: true,
    columns: [{ data: 'name', render: function (d) { return '<span class="fw-semibold">' + SMS.esc(d) + '</span>'; } }, { data: 'phone', render: function (d) { return d ? SMS.esc(d) : '–'; } },
        { data: 'email', render: function (d) { return d ? SMS.esc(d) : '–'; } }, { data: 'items', className: 'text-center' }],
    fields: [{ name: 'name', label: 'Supplier name', required: true, max: 100, col: 12, unique: true }, { name: 'phone', label: 'Phone', type: 'tel', max: 20, col: 6, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
        { name: 'email', label: 'Email', type: 'email', max: 120, col: 6 }, { name: 'address', label: 'Address', max: 255, col: 12 }] });
</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
