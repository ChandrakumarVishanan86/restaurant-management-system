<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::FLOOR);
$waiters = db()->query("SELECT id, full_name FROM users WHERE role = 'waiter' AND is_active = 1 ORDER BY full_name")->fetchAll();
$pageTitle = 'Tables'; $activeNav = 'tables'; $libs = []; $pageScripts = ['assets/js/tables.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap gap-3 small text-muted"><span><i class="bi bi-circle-fill text-success"></i> Available</span><span><i class="bi bi-circle-fill text-danger"></i> Occupied</span><span><i class="bi bi-circle-fill text-warning"></i> Cleaning</span><span><i class="bi bi-calendar2-heart text-primary"></i> Reservation today</span></div>
    <div class="ms-auto d-flex gap-2"><button class="btn btn-soft" id="btnOther"><i class="bi bi-bag me-1"></i> Takeaway / Delivery</button>
        <?php if ($user['role'] === 'admin'): ?><a class="btn btn-soft" href="<?= url('tables/manage') ?>"><i class="bi bi-gear me-1"></i> Manage tables</a><?php endif; ?></div>
</div>
<div id="floor"></div>

<div class="modal fade" id="openModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="openForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="openTitle">Open order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="btn-group w-100 mb-3" id="typeSel" style="display:none"><input type="radio" class="btn-check" name="otype" id="tTake" value="Takeaway" checked><label class="btn btn-outline-primary" for="tTake">Takeaway</label>
            <input type="radio" class="btn-check" name="otype" id="tDel" value="Delivery"><label class="btn btn-outline-primary" for="tDel">Delivery</label></div>
        <div class="row g-3">
            <div class="col-6" id="guestsCol"><label class="form-label">Guests</label><input type="number" class="form-control" id="oGuests" min="1" max="30" value="2"></div>
            <?php if ($user['role'] !== 'waiter'): ?><div class="col-6" id="waiterCol"><label class="form-label">Waiter</label><select class="form-select" id="oWaiter"><option value="">— None —</option><?php foreach ($waiters as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-12 position-relative"><label class="form-label">Customer <small class="text-muted">(optional for dine-in)</small></label><input class="form-control" id="oCust" placeholder="Search name, code or phone…" autocomplete="off"><input type="hidden" id="oCid">
                <div class="list-group position-absolute w-100 shadow" id="oCustList" style="z-index:5;display:none"></div><div class="form-text" id="oCustPicked"></div></div>
            <div class="col-md-6 other-only"><label class="form-label">Name</label><input class="form-control" id="oName" maxlength="120"></div>
            <div class="col-md-6 other-only"><label class="form-label">Phone</label><input class="form-control" id="oPhone" maxlength="20"></div>
            <div class="col-12 other-only" id="addrCol" style="display:none"><label class="form-label">Delivery address</label><input class="form-control" id="oAddr" maxlength="255"></div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" id="oNote" maxlength="255" placeholder="Allergies, special occasion…"></div>
        </div>
    </div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient"><i class="bi bi-play-circle me-1"></i> Start order</button></div>
</form></div></div>
<?php ob_start(); ?><script>window.TBL = { role: <?= json_encode($user['role']) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
