<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'chef']);
$cats = array_map(static fn($c) => [$c['id'], $c['name']], MenuItemModel::categories());
$pageTitle = 'Menu & Recipes'; $activeNav = 'menu'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/menu.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div><h5 class="mb-0 fw-bold">Menu &amp; recipes</h5><small class="text-muted">Recipes drive automatic stock deduction when the kitchen starts a dish. Toggle <em>Available</em> to mark a dish sold out.</small></div>
        <div class="ms-auto d-flex gap-2"><?php if ($user['role'] === 'admin'): ?><a class="btn btn-soft" href="<?= url('menu/categories') ?>"><i class="bi bi-tags me-1"></i> Categories</a><?php endif; ?>
        <button class="btn btn-gradient" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add Item</button></div></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Item</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Food cost</th><th class="text-center">Prep</th><th class="text-center">Sold</th><th>Today</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>

<div class="modal fade" id="recipeModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="rcTitle">Recipe</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="text-muted small">Quantities are per portion, in each ingredient's own unit.</p><div id="rcLines"></div>
        <button class="btn btn-sm btn-soft mt-2" id="rcAdd"><i class="bi bi-plus-lg"></i> Add ingredient</button><div class="mt-3 small" id="rcSummary"></div></div>
    <div class="modal-footer border-0"><button class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-gradient" id="rcSave"><i class="bi bi-check2-circle me-1"></i> Save recipe</button></div>
</div></div></div>
<?php ob_start(); ?><script>window.MENU = { cats: <?= json_encode($cats) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
