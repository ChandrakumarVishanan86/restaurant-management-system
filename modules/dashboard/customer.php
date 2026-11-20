<?php
$d = DashboardModel::customer((int) $user['customer_id']); $p = $d['profile'];
$pageTitle = 'My Dashboard'; $activeNav = 'dashboard'; $libs = []; $pageScripts = [];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="mb-3"><h4 class="fw-bold mb-0">Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?> 🍽️</h4><div class="text-muted small">Hungry? Order online or book a table.</div></div>
<div class="row g-3 mb-4">
    <?= stat_card('stat-amber', 'bi-star-fill', $p['loyalty_points'] ?? 0, 'Loyalty points (= ' . CURRENCY . ' off)') ?><?= stat_card('stat-indigo', 'bi-bag-check', $p['visits'] ?? 0, 'Visits') ?>
    <?= stat_card('stat-green', 'bi-wallet2', $p['total_spent'] ?? 0, 'Total spent', 2, CURRENCY) ?>
    <div class="col-sm-6 col-xl-3"><a class="btn btn-gradient w-100 h-100 d-flex align-items-center justify-content-center fs-6" href="<?= url('order-online') ?>"><i class="bi bi-bag-plus me-2"></i> Order online</a></div>
</div>
<?php if ($d['unpaid']): ?><div class="alert alert-warning">You have an unpaid bill: <?php foreach ($d['unpaid'] as $u): ?><a href="<?= url('bills/' . (int) $u['id']) ?>"><?= e($u['bill_no']) ?></a> (<?= money($u['total'] - $u['paid']) ?>) <?php endforeach; ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-xl-5"><div class="card-panel h-100"><h6 class="fw-bold">Recent orders</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['orders'] as $o): ?><li><div><a class="fw-semibold" href="<?= url('orders/' . (int) $o['id']) ?>"><?= e($o['order_no']) ?></a><div class="small text-muted"><?= e($o['type']) ?> · <?= fdatetime($o['created_at']) ?> · <?= money($o['subtotal']) ?></div></div><span class="ms-auto"><?= status_badge($o['status']) ?></span></li><?php endforeach; ?><?php if (!$d['orders']): ?><li class="text-muted">No orders yet.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><div class="d-flex"><h6 class="fw-bold">Upcoming reservations</h6><a class="ms-auto small" href="<?= url('reservations') ?>">Book →</a></div><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['reservations'] as $r): ?><li><div><strong><?= fdate($r['res_date']) ?> <?= e(substr((string) $r['res_time'], 0, 5)) ?></strong><div class="small text-muted">Party of <?= (int) $r['party_size'] ?><?= $r['table_no'] ? ' · Table ' . e($r['table_no']) : '' ?></div></div></li><?php endforeach; ?><?php if (!$d['reservations']): ?><li class="text-muted">None.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-3"><div class="card-panel h-100"><h6 class="fw-bold">Your favourites</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['favourites'] as $f): ?><li><strong><?= e($f['name']) ?></strong><span class="ms-auto badge badge-soft-secondary">×<?= (int) $f['qty'] ?></span></li><?php endforeach; ?><?php if (!$d['favourites']): ?><li class="text-muted">Order something!</li><?php endif; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
