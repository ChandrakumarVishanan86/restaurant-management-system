<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'waiter', 'cashier', 'customer']);
$o = OrderModel::find((int) ($_GET['id'] ?? 0));
if (!$o || !OrderModel::canAccess($user, $o)) { http_response_code(404); exit('Order not found.'); }
$isFloor = in_array($user['role'], Auth::FLOOR, true);
$free = $isFloor ? array_values(array_filter(TableModel::floor(), static fn($t) => $t['status'] === 'Available')) : [];
$pageTitle = 'Order ' . $o['order_no']; $activeNav = 'orders'; $libs = []; $pageScripts = ['assets/js/pos.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div id="posRoot" class="<?= $isFloor ? 'pos-wrap' : '' ?>">
<?php if ($isFloor): ?>
    <div class="pos-menu card-panel">
        <div class="d-flex gap-2 mb-2"><input class="form-control" id="mSearch" placeholder="Search menu…"><div class="form-check form-switch pt-2 ms-2 text-nowrap"><input class="form-check-input" type="checkbox" id="vegOnly"><label class="form-check-label small" for="vegOnly">Veg</label></div></div>
        <div class="cat-chips mb-3" id="catChips"></div><div class="menu-grid" id="menuGrid"></div>
    </div>
<?php endif; ?>
    <div class="pos-side card-panel" id="orderPanel"><div class="text-muted">Loading…</div></div>
</div>

<?php if ($isFloor): ?>
<div class="modal fade" id="noteModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3">
    <h6 class="fw-bold" id="nmName"></h6><div class="d-flex align-items-center gap-2 mb-2"><label class="small">Qty</label><input type="number" id="nmQty" class="form-control form-control-sm" min="1" max="50" value="1" style="width:80px"></div>
    <input class="form-control form-control-sm mb-3" id="nmNote" maxlength="120" placeholder="Note: no onion, extra spicy…"><button class="btn btn-gradient w-100" id="nmAdd">Add to order</button></div></div></div>

<div class="modal fade" id="billModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Generate bill</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-2 mb-3" id="discRow"><div class="col-6"><label class="form-label small">Discount</label><input type="number" id="bdVal" class="form-control" min="0" step="0.01" value="0"></div>
        <div class="col-6"><label class="form-label small">Type</label><select id="bdType" class="form-select"><option value="amount">Amount</option><option value="percent">Percent %</option></select></div></div>
        <div class="mb-3" id="ptsRow"><label class="form-label small">Redeem loyalty points <span class="text-muted" id="ptsAvail"></span></label><input type="number" id="bdPts" class="form-control" min="0" step="1" value="0"></div>
        <div id="quoteBox"></div></div>
    <div class="modal-footer border-0"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" id="bdGo">Generate bill</button></div></div></div></div>

<div class="modal fade" id="moveModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3"><h6 class="fw-bold">Move to table</h6>
    <select id="mvTable" class="form-select mb-3"><?php foreach ($free as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['table_no']) ?> · <?= e($t['area']) ?> · <?= (int) $t['seats'] ?> seats</option><?php endforeach; ?></select>
    <button class="btn btn-gradient w-100" id="mvGo" <?= $free ? '' : 'disabled' ?>>Move</button></div></div></div>
<?php endif; ?>
<?php ob_start(); ?><script>window.ORD = { id: <?= (int) $o['id'] ?>, floor: <?= $isFloor ? 'true' : 'false' ?>, role: <?= json_encode($user['role']) ?>, cur: <?= json_encode(CURRENCY) ?>, svc: <?= SERVICE_CHARGE_PCT ?>, tax: <?= TAX_PCT ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
