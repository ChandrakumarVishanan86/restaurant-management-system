<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin', 'chef']);
$sup = array_map(static fn($s) => [$s['id'], $s['name']], db()->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll());
$low = IngredientModel::low();
$val = (float) db()->query('SELECT COALESCE(SUM(stock * cost_per_unit),0) FROM ingredients WHERE is_active = 1')->fetchColumn();
$pageTitle = 'Inventory'; $activeNav = 'inventory'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/inventory.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card-panel py-3"><div class="small text-muted">Stock value</div><div class="fs-4 fw-bold"><?= money($val) ?></div></div></div>
    <div class="col-md-8"><div class="card-panel py-3"><div class="small text-muted mb-1">Running low (<?= count($low) ?>)</div><?php foreach ($low as $l): ?><span class="badge <?= (float) $l['stock'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> me-1"><?= e($l['name']) ?>: <?= (float) $l['stock'] ?> <?= e($l['unit']) ?></span><?php endforeach; ?><?php if (!$low): ?><span class="text-success"><i class="bi bi-check-circle"></i> Everything is above reorder level.</span><?php endif; ?></div></div>
</div>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Ingredients &amp; stock</h5><small class="text-muted">Stock reduces automatically when the kitchen starts a dish. Receive deliveries, record wastage, or adjust after a stock-take.</small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add Ingredient</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Ingredient</th><th>Stock</th><th class="text-end">Reorder at</th><th class="text-end">Cost / unit</th><th class="text-end">Value</th><th>Supplier</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<div class="modal fade" id="stockModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="stockForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="stTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="small text-muted mb-3" id="stInfo"></div><div class="row g-3" id="stFields"></div></div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient">Save</button></div></form></div></div>
<div class="modal fade" id="histModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="hiTitle">History</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="hiBody"></div></div></div></div>
<?php ob_start(); ?><script>window.INV = { suppliers: <?= json_encode($sup) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
