<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-29 days'))); $to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!valid_date($from)) { $from = date('Y-m-d', strtotime('-29 days')); } if (!valid_date($to)) { $to = date('Y-m-d'); }
if ($from > $to) { [$from, $to] = [$to, $from]; }
$r = ReportModel::summary($from, $to); $h = $r['head'];
$pageTitle = 'Reports'; $activeNav = 'reports'; $libs = ['chart', 'flatpickr']; $pageScripts = ['assets/js/reports.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$margin = (float) $h['gross'] > 0 ? round((1 - (float) $h['food_cost'] / (float) $h['gross']) * 100, 1) : 0;
?>
<form class="card-panel mb-4 d-flex flex-wrap gap-2 align-items-end" method="get"><div><label class="form-label small mb-0">From</label><input class="form-control" id="from" name="from" value="<?= e($from) ?>"></div><div><label class="form-label small mb-0">To</label><input class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-gradient">Apply</button><a class="btn btn-soft ms-auto" href="<?= url('reports/export?from=' . urlencode($from) . '&to=' . urlencode($to)) ?>"><i class="bi bi-download"></i> Export bills CSV</a></form>
<div class="row g-3 mb-4">
    <?= stat_card('stat-indigo', 'bi-cash-coin', $h['sales'], 'Sales (incl. tax)', 2, CURRENCY) ?><?= stat_card('stat-green', 'bi-receipt', $h['orders'], 'Paid orders') ?>
    <?= stat_card('stat-cyan', 'bi-graph-up', $h['avg_bill'], 'Average bill', 2, CURRENCY) ?><?= stat_card('stat-amber', 'bi-percent', $margin, 'Gross food margin %', 1) ?>
</div>
<div class="row g-3 mb-4">
<?php foreach ([['Gross (before discount)', $h['gross']], ['Discounts', $h['discounts']], ['Points redeemed', $h['points_value']], ['Service charge', $h['service']], ['VAT collected', $h['tax']], ['Food cost', $h['food_cost']], ['Wastage', $h['wastage_cost']], ['Outstanding bills', $h['outstanding']]] as [$l, $v]): ?>
    <div class="col-6 col-md-3"><div class="card-panel py-2"><div class="small text-muted"><?= e($l) ?></div><div class="fw-bold"><?= money($v) ?></div></div></div><?php endforeach; ?>
</div>
<div class="row g-4 mb-4">
    <div class="col-xl-8"><div class="card-panel"><h6 class="fw-bold">Daily sales</h6><div class="chart-box"><canvas id="chartDaily"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel"><h6 class="fw-bold">Payment methods</h6><div class="chart-box"><canvas id="chartMethods"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel"><h6 class="fw-bold">By order type</h6><div class="chart-box"><canvas id="chartTypes"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel"><h6 class="fw-bold">By category</h6><div class="chart-box"><canvas id="chartCats"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card-panel"><h6 class="fw-bold">Orders by hour</h6><div class="chart-box"><canvas id="chartHours"></canvas></div></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Top dishes</h6><table class="table table-sm"><tbody><?php foreach ($r['top_items'] as $i): ?><tr><td><?= e($i['name']) ?></td><td class="text-end"><?= (int) $i['qty'] ?></td><td class="text-end"><?= money($i['revenue']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Waiter performance</h6><table class="table table-sm"><thead><tr><th>Waiter</th><th class="text-end">Orders</th><th class="text-end">Sales</th><th class="text-end">Avg</th></tr></thead><tbody><?php foreach ($r['waiters'] as $w): ?><tr><td><?= e($w['full_name']) ?></td><td class="text-end"><?= (int) $w['orders'] ?></td><td class="text-end"><?= money($w['sales']) ?></td><td class="text-end"><?= money($w['avg_bill']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">Best customers</h6><table class="table table-sm"><tbody><?php foreach ($r['customers'] as $c): ?><tr><td><?= e($c['full_name']) ?></td><td class="text-end"><?= (int) $c['orders'] ?> orders</td><td class="text-end"><?= money($c['spent']) ?></td></tr><?php endforeach; ?></tbody></table>
        <h6 class="fw-bold mt-3">Reservations</h6><?php foreach ($r['reservations'] as $x): ?><span class="badge badge-soft-secondary me-1"><?= e($x['label']) ?>: <?= (int) $x['value'] ?></span><?php endforeach; ?></div></div>
</div>
<?php ob_start(); ?><script>window.REPORT = <?= json_encode(['daily' => array_map(static fn($d) => ['label' => $d['d'], 'value' => $d['value']], $r['daily']), 'methods' => $r['methods'], 'types' => $r['by_type'], 'cats' => $r['categories'], 'hours' => $r['hourly']]) ?>;</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
