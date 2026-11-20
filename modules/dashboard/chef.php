<?php
$d = DashboardModel::chef();
$pageTitle = 'Dashboard'; $activeNav = 'dashboard'; $libs = []; $pageScripts = [];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="mb-3"><h4 class="fw-bold mb-0">Kitchen overview 👨‍🍳</h4><div class="text-muted small"><?= date('l, d M Y') ?></div></div>
<div class="row g-3 mb-4">
    <?= stat_card('stat-cyan', 'bi-clock', $d['stats']['waiting'], 'Waiting') ?><?= stat_card('stat-amber', 'bi-fire', $d['stats']['cooking'], 'Cooking') ?>
    <?= stat_card('stat-green', 'bi-check2-circle', $d['stats']['ready'], 'Ready at pass') ?><?= stat_card('stat-indigo', 'bi-stopwatch', (float) $d['stats']['avg_prep'], 'Avg prep minutes today', 1) ?>
</div>
<div class="row g-4">
    <div class="col-xl-5"><div class="card-panel h-100"><div class="d-flex"><h6 class="fw-bold">Ticket queue</h6><a class="ms-auto small" href="<?= url('kitchen') ?>">Open kitchen display →</a></div><ul class="list-unstyled mini-list mb-0"><?php foreach (array_slice($d['queue'], 0, 6) as $g): ?><li><div><strong><?= $g['table_no'] ? 'T' . e($g['table_no']) : e($g['type']) ?></strong> <span class="small text-muted"><?= e($g['order_no']) ?> · KOT <?= (int) $g['kot_no'] ?></span><div class="small"><?= e(implode(', ', array_map(static fn($i) => $i['qty'] . '× ' . $i['name'], $g['items']))) ?></div></div></li><?php endforeach; ?><?php if (!$d['queue']): ?><li class="text-muted">No tickets.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><div class="d-flex"><h6 class="fw-bold">Low stock</h6><a class="ms-auto small" href="<?= url('inventory') ?>">Inventory →</a></div><?php foreach ($d['low_stock'] as $l): ?><div class="d-flex justify-content-between small py-1 border-bottom"><span><?= e($l['name']) ?></span><strong class="<?= (float) $l['stock'] <= 0 ? 'text-danger' : 'text-warning' ?>"><?= (float) $l['stock'] ?> <?= e($l['unit']) ?></strong></div><?php endforeach; ?><?php if (!$d['low_stock']): ?><span class="text-success small">Everything above reorder level.</span><?php endif; ?></div></div>
    <div class="col-xl-3"><div class="card-panel h-100"><h6 class="fw-bold">Sold out</h6><?php foreach ($d['soldout'] as $s): ?><span class="badge bg-danger me-1 mb-1"><?= e($s['name']) ?></span><?php endforeach; ?><?php if (!$d['soldout']): ?><span class="small text-success">Nothing sold out.</span><?php endif; ?><div class="mt-2"><a class="small" href="<?= url('menu') ?>">Manage availability →</a></div></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
