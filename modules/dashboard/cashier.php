<?php
$d = DashboardModel::cashier();
$pageTitle = 'Dashboard'; $activeNav = 'dashboard'; $libs = ['chart']; $pageScripts = ['assets/js/dashboard.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="mb-3"><h4 class="fw-bold mb-0">Cashier desk 💳</h4><div class="text-muted small"><?= date('l, d M Y') ?></div></div>
<div class="row g-3 mb-4">
    <?= stat_card('stat-green', 'bi-cash-stack', $d['collected'], 'Collected today', 2, CURRENCY) ?><?= stat_card('stat-indigo', 'bi-receipt', $d['today']['orders'], 'Bills settled today') ?>
    <?= stat_card('stat-amber', 'bi-hourglass-split', count($d['billable']), 'Ready to bill') ?><?= stat_card('stat-red', 'bi-exclamation-circle', count($d['due']), 'Bills awaiting payment') ?>
</div>
<div class="row g-4">
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Ready to bill</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['billable'] as $b): ?><li><div><a class="fw-semibold" href="<?= url('orders/' . (int) $b['id']) ?>"><?= e($b['order_no']) ?></a><div class="small text-muted"><?= e($b['table_no'] ? 'Table ' . $b['table_no'] : $b['type']) ?> · <?= e($b['customer_name']) ?></div></div><span class="ms-auto fw-semibold"><?= money($b['subtotal']) ?></span></li><?php endforeach; ?><?php if (!$d['billable']): ?><li class="text-muted">None.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Awaiting payment</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['due'] as $b): ?><li><div><a class="fw-semibold" href="<?= url('bills/' . (int) $b['id']) ?>"><?= e($b['bill_no']) ?></a><div class="small text-muted"><?= e($b['table_no'] ? 'Table ' . $b['table_no'] : $b['customer_name']) ?></div></div><span class="ms-auto text-danger fw-semibold"><?= money($b['total'] - $b['paid']) ?></span></li><?php endforeach; ?><?php if (!$d['due']): ?><li class="text-muted">All settled.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Collections — 14 days</h6><div class="chart-box"><canvas id="chartCollect"></canvas></div></div></div>
</div>
<?php ob_start(); ?><script>window.DASH = { collect: <?= json_encode($d['chart']) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
