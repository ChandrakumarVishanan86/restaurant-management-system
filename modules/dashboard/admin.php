<?php
$d = DashboardModel::admin();
$pageTitle = 'Dashboard'; $activeNav = 'dashboard'; $libs = ['chart']; $pageScripts = ['assets/js/dashboard.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="mb-3"><h4 class="fw-bold mb-0">Good day, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h4><div class="text-muted small"><?= date('l, d M Y') ?></div></div>
<div class="row g-3 mb-4">
    <?= stat_card('stat-indigo', 'bi-cash-coin', $d['today']['sales'], "Today's sales", 2, CURRENCY) ?>
    <?= stat_card('stat-green', 'bi-receipt', $d['today']['orders'], 'Orders paid today') ?>
    <?= stat_card('stat-cyan', 'bi-grid-3x3-gap', (int) $d['tables']['occupied'], 'Tables occupied (of ' . (int) $d['tables']['total'] . ')') ?>
    <?= stat_card('stat-red', 'bi-hourglass-split', $d['unpaid'], 'Unpaid bills', 2, CURRENCY) ?>
</div>
<div class="row g-4 mb-4">
    <div class="col-xl-8"><div class="card-panel"><h6 class="fw-bold">Sales — last 14 days</h6><div class="chart-box"><canvas id="chartSales"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel"><h6 class="fw-bold">Revenue by order type (30 d)</h6><div class="chart-box"><canvas id="chartType"></canvas></div></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Top dishes (30 d)</h6><div class="chart-box"><canvas id="chartTop"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Today's reservations</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['reservations'] as $r): ?><li><div><strong><?= e($r['time']) ?></strong> <?= e($r['guest_name']) ?><div class="small text-muted">Party of <?= (int) $r['party_size'] ?><?= $r['table_no'] ? ' · ' . e($r['table_no']) : '' ?></div></div><span class="ms-auto"><?= status_badge($r['status']) ?></span></li><?php endforeach; ?><?php if (!$d['reservations']): ?><li class="text-muted">None today.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Attention</h6>
        <div class="small mb-2"><i class="bi bi-fire text-warning"></i> Kitchen: <strong><?= (int) $d['kitchen']['waiting'] ?></strong> waiting · <strong><?= (int) $d['kitchen']['cooking'] ?></strong> cooking · <strong><?= (int) $d['kitchen']['ready'] ?></strong> ready</div>
        <div class="small mb-2"><i class="bi bi-phone text-info"></i> Online orders in progress: <strong><?= $d['online_open'] ?></strong></div>
        <div class="small fw-semibold mt-3">Low stock</div><?php foreach ($d['low_stock'] as $l): ?><span class="badge bg-warning text-dark me-1"><?= e($l['name']) ?></span><?php endforeach; ?><?php if (!$d['low_stock']): ?><span class="small text-success">All good</span><?php endif; ?>
        <div class="small fw-semibold mt-3">Sold out</div><?php foreach ($d['soldout'] as $l): ?><span class="badge bg-danger me-1"><?= e($l['name']) ?></span><?php endforeach; ?><?php if (!$d['soldout']): ?><span class="small text-success">Nothing</span><?php endif; ?></div></div>
</div>
<?php ob_start(); ?><script>window.DASH = { sales: <?= json_encode($d['sales_chart']) ?>, types: <?= json_encode($d['type_chart']) ?>, top: <?= json_encode($d['top_items']) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
