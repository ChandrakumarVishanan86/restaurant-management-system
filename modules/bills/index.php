<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'cashier', 'customer']);
$isC = $user['role'] === 'customer';
$billable = $isC ? [] : BillModel::billable();
$pageTitle = $isC ? 'My Bills' : 'Billing'; $activeNav = 'bills'; $libs = ['datatables']; $pageScripts = ['assets/js/bills.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<?php if (!$isC): ?>
<div class="card-panel mb-4"><h6 class="fw-bold mb-2"><i class="bi bi-hourglass-split text-warning"></i> Ready to bill (<?= count($billable) ?>)</h6>
    <?php if ($billable): ?><div class="row g-2"><?php foreach ($billable as $b): ?><div class="col-md-6 col-xl-3"><a class="d-block p-3 border rounded-3 text-decoration-none text-dark h-100" href="<?= url('orders/' . (int) $b['id']) ?>">
        <div class="fw-bold"><?= e($b['order_no']) ?> <span class="badge badge-soft-secondary"><?= e($b['table_no'] ? 'T' . $b['table_no'] : $b['type']) ?></span></div><div class="small text-muted"><?= e($b['customer_name']) ?></div><div class="fw-bold text-primary"><?= money($b['subtotal']) ?></div></a></div><?php endforeach; ?></div>
    <?php else: ?><div class="text-muted small">Nothing waiting. Orders appear here once every dish is ready or served.</div><?php endif; ?></div>
<?php endif; ?>
<div class="card-panel">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><h5 class="mb-0 fw-bold">Bills</h5>
        <div class="btn-group ms-auto" id="bF"><button class="btn btn-sm btn-soft active" data-s="">All</button><button class="btn btn-sm btn-soft" data-s="due">Due</button><button class="btn btn-sm btn-soft" data-s="Paid">Paid</button></div></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Bill</th><th>Order</th><th>Customer</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th></tr></thead></table></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
