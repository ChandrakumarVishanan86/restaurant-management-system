<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['customer']);
$s = db()->prepare('SELECT address FROM customers WHERE id = :i'); $s->execute([':i' => (int) $user['customer_id']]); $addr = (string) $s->fetchColumn();
$pageTitle = 'Order Online'; $activeNav = 'order-online'; $libs = []; $pageScripts = ['assets/js/portal.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="pos-wrap">
    <div class="pos-menu card-panel"><div class="d-flex gap-2 mb-2"><input class="form-control" id="mSearch" placeholder="Search dishes…"><div class="form-check form-switch pt-2 ms-2 text-nowrap"><input class="form-check-input" type="checkbox" id="vegOnly"><label class="form-check-label small" for="vegOnly">Veg</label></div></div>
        <div class="cat-chips mb-3" id="catChips"></div><div class="menu-grid" id="menuGrid"></div></div>
    <div class="pos-side card-panel"><h6 class="fw-bold">Your order</h6>
        <div class="btn-group w-100 mb-3" id="typeSel"><button class="btn btn-sm btn-gradient" data-t="Takeaway">Takeaway</button><button class="btn btn-sm btn-soft" data-t="Delivery">Delivery</button></div>
        <div id="cartLines" class="mb-2"><div class="text-muted small">Your cart is empty.</div></div>
        <div id="addrBox" class="mb-2" style="display:none"><label class="form-label small">Delivery address</label><input class="form-control" id="cAddr" maxlength="255" value="<?= e($addr) ?>"></div>
        <input class="form-control mb-2" id="cNote" maxlength="255" placeholder="Notes for the kitchen">
        <div class="receipt-row"><span>Items total</span><strong id="cTotal">–</strong></div><div class="small text-muted mb-2">Service charge (dine-in only) and <?= TAX_PCT ?>% VAT are added on the bill. Pay at pickup / on delivery.</div>
        <button class="btn btn-gradient w-100" id="cGo" disabled>Place order</button></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
