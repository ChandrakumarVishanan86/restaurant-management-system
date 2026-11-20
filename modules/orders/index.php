<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'waiter', 'cashier', 'customer']);
$pageTitle = $user['role'] === 'customer' ? 'My Orders' : 'Orders'; $activeNav = 'orders'; $libs = ['datatables']; $pageScripts = ['assets/js/orders.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$isC = $user['role'] === 'customer';
?>
<div class="card-panel">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div><h5 class="mb-0 fw-bold"><?= $isC ? 'My orders' : 'Orders' ?></h5><small class="text-muted"><?= $isC ? 'Track your online orders.' : 'Every dine-in, takeaway and delivery order.' ?></small></div>
        <div class="ms-auto d-flex gap-2"><div class="btn-group" id="stChips"><button class="btn btn-sm btn-soft active" data-s="">All</button><button class="btn btn-sm btn-soft" data-s="active">Active</button><button class="btn btn-sm btn-soft" data-s="Paid">Paid</button><button class="btn btn-sm btn-soft" data-s="Cancelled">Cancelled</button></div>
        <a class="btn btn-gradient btn-sm" href="<?= url($isC ? 'order-online' : 'tables') ?>"><i class="bi bi-plus-lg"></i> <?= $isC ? 'Order online' : 'New order' ?></a></div></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Order</th><th>Type</th><th>Table</th><th>Customer</th><th class="text-center">Items</th><th class="text-end">Amount</th><th>Opened</th><th>Status</th></tr></thead></table></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
