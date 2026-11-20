<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'waiter', 'cashier', 'customer']);
$isC = $user['role'] === 'customer';
$me = null;
if ($isC) { $s = db()->prepare('SELECT full_name, phone FROM customers WHERE id = :i'); $s->execute([':i' => (int) $user['customer_id']]); $me = $s->fetch(); }
$pageTitle = $isC ? 'Book a Table' : 'Reservations'; $activeNav = 'reservations'; $libs = ['datatables', 'flatpickr']; $pageScripts = ['assets/js/reservations.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div><h5 class="mb-0 fw-bold"><?= $isC ? 'My reservations' : 'Reservations' ?></h5><small class="text-muted">Open <?= e(OPEN_TIME) ?>–<?= e(CLOSE_TIME) ?> · bookings assume a <?= RES_STAY_MINUTES ?>-minute stay.</small></div>
        <div class="ms-auto d-flex gap-2"><?php if (!$isC): ?><div class="btn-group" id="rF"><button class="btn btn-sm btn-soft active" data-f="today">Today</button><button class="btn btn-sm btn-soft" data-f="upcoming">Upcoming</button><button class="btn btn-sm btn-soft" data-f="past">Past</button></div><?php endif; ?>
        <button class="btn btn-gradient btn-sm" id="btnBook"><i class="bi bi-calendar-plus me-1"></i> Book a table</button></div></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Ref</th><th>Guest</th><th>When</th><th class="text-center">Party</th><th>Table</th><th>Status</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<div class="modal fade" id="bkModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="bkForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Book a table</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
        <?php if (!$isC): ?><div class="col-12 position-relative"><label class="form-label">Customer (optional)</label><input class="form-control" id="bkCust" placeholder="Search name or phone…" autocomplete="off"><input type="hidden" name="customer_id" id="bkCid"><div class="list-group position-absolute w-100 shadow" id="bkRes" style="z-index:5"></div></div>
        <div class="col-7"><label class="form-label">Guest name *</label><input class="form-control" name="guest_name" id="bkName" maxlength="120"></div><div class="col-5"><label class="form-label">Phone *</label><input class="form-control" name="phone" id="bkPhone" maxlength="20"></div>
        <?php else: ?><div class="col-12 small text-muted">Booking for <strong><?= e($me['full_name'] ?? '') ?></strong> (<?= e($me['phone'] ?? '') ?>)</div><?php endif; ?>
        <div class="col-6"><label class="form-label">Date *</label><input class="form-control" name="res_date" id="bkDate" autocomplete="off"></div>
        <div class="col-6"><label class="form-label">Guests *</label><input type="number" class="form-control" name="party_size" id="bkParty" min="1" max="30" value="2"></div>
        <div class="col-12"><label class="form-label">Time *</label><div id="bkSlots" class="d-flex flex-wrap gap-2"><span class="text-muted small">Pick a date and party size.</span></div><input type="hidden" name="res_time" id="bkTime"></div>
        <div class="col-12"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="255" placeholder="Birthday, high chair, window seat…"></div></div></div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-gradient">Confirm booking</button></div></form></div></div>
<div class="modal fade" id="seatModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3"><h6 class="fw-bold">Seat guests</h6><div class="small text-muted mb-2" id="stInfo"></div>
    <select id="stTable" class="form-select mb-3"></select><button class="btn btn-gradient w-100" id="stGo">Seat &amp; open order</button></div></div></div>
<?php ob_start(); ?><script>window.RES = { customer: <?= $isC ? 'true' : 'false' ?>, role: <?= json_encode($user['role']) ?>, today: <?= json_encode(date('Y-m-d')) ?>, max: <?= json_encode(date('Y-m-d', strtotime('+' . RES_DAYS_AHEAD . ' days'))) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
