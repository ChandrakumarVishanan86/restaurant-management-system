<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'cashier', 'waiter', 'customer']);
$b = BillModel::find((int) ($_GET['id'] ?? 0));
if (!$b || !BillModel::canAccess($user, $b)) { http_response_code(404); exit('Bill not found.'); }
$items = BillModel::items((int) $b['order_id']); $pays = BillModel::payments((int) $b['id']);
$canPay = in_array($user['role'], ['admin', 'cashier'], true);
$pageTitle = 'Bill ' . $b['bill_no']; $activeNav = 'bills'; $libs = []; $pageScripts = ['assets/js/billview.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="row g-4">
<div class="col-lg-7"><div class="card-panel">
    <div class="d-flex align-items-start mb-3"><div><h4 class="fw-bold mb-0"><?= e($b['bill_no']) ?></h4><div class="text-muted small"><?= fdatetime($b['created_at']) ?> · <a href="<?= url('orders/' . (int) $b['order_id']) ?>"><?= e($b['order_no']) ?></a> · <?= e($b['type']) ?><?= $b['table_no'] ? ' · Table ' . e($b['table_no']) : '' ?></div>
        <div class="small"><?= e($b['customer_name']) ?><?= $b['waiter_name'] ? ' · served by ' . e($b['waiter_name']) : '' ?></div></div>
        <div class="ms-auto text-end"><?= status_badge($b['status']) ?><div class="mt-2"><a class="btn btn-sm btn-soft" target="_blank" href="<?= url('bills/' . (int) $b['id'] . '/pdf') ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div></div></div>
    <table class="table table-sm align-middle"><thead><tr><th>Item</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?></td><td class="text-end"><?= money($i['unit_price']) ?></td><td class="text-center"><?= (int) $i['qty'] ?></td><td class="text-end"><?= money($i['amount']) ?></td></tr><?php endforeach; ?></tbody></table>
    <div class="receipt-row"><span>Subtotal</span><span><?= money($b['subtotal']) ?></span></div>
    <?php if ((float) $b['discount'] > 0): ?><div class="receipt-row"><span>Discount</span><span>− <?= money($b['discount']) ?></span></div><?php endif; ?>
    <?php if ((int) $b['points_used'] > 0): ?><div class="receipt-row"><span>Loyalty points (<?= (int) $b['points_used'] ?>)</span><span>− <?= money($b['points_used'] * POINT_VALUE) ?></span></div><?php endif; ?>
    <?php if ((float) $b['service_charge'] > 0): ?><div class="receipt-row"><span>Service charge (<?= SERVICE_CHARGE_PCT ?>%)</span><span><?= money($b['service_charge']) ?></span></div><?php endif; ?>
    <div class="receipt-row"><span>VAT (<?= TAX_PCT ?>% on base + service)</span><span><?= money($b['tax']) ?></span></div>
    <div class="receipt-total"><span>Total</span><span><?= money($b['total']) ?></span></div>
</div></div>
<div class="col-lg-5">
    <div class="card-panel mb-4"><h6 class="fw-bold">Payments</h6>
        <div class="d-flex justify-content-between"><span>Paid</span><strong class="text-success"><?= money($b['paid']) ?></strong></div><div class="d-flex justify-content-between mb-2"><span>Balance</span><strong class="<?= (float) $b['balance'] > 0 ? 'text-danger' : '' ?>"><?= money($b['balance']) ?></strong></div>
        <ul class="list-unstyled mini-list mb-0"><?php foreach ($pays as $p): ?><li><div><strong><?= e($p['method']) ?></strong><div class="small text-muted"><?= fdatetime($p['paid_at'] ?? $p['created_at'] ?? null) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></div></div><span class="ms-auto fw-semibold"><?= money($p['amount']) ?></span></li><?php endforeach; ?><?php if (!$pays): ?><li class="text-muted">No payments yet.</li><?php endif; ?></ul></div>
    <?php if ($canPay && $b['status'] !== 'Paid'): ?>
    <div class="card-panel"><h6 class="fw-bold">Take payment</h6><form id="payForm" class="row g-2" novalidate>
        <div class="col-6"><label class="form-label small">Amount</label><input type="number" class="form-control" name="amount" step="0.01" min="0.01" max="<?= e((string) $b['balance']) ?>" value="<?= e((string) $b['balance']) ?>"></div>
        <div class="col-6"><label class="form-label small">Method</label><select class="form-select" name="method"><option>Cash</option><option>Card</option><option>Online</option></select></div>
        <div class="col-12"><input class="form-control" name="reference" maxlength="60" placeholder="Reference / last 4 digits (optional)"></div>
        <div class="col-12 d-grid"><button class="btn btn-gradient">Record payment</button></div></form>
        <?php if ((float) $b['paid'] == 0.0): ?><button class="btn btn-soft-danger w-100 mt-2" id="btnReopen">Cancel bill &amp; reopen order</button><?php endif; ?></div>
    <?php endif; ?>
</div></div>
<?php ob_start(); ?><script>window.BILL = { id: <?= (int) $b['id'] ?>, order: <?= (int) $b['order_id'] ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
