<?php
$d = DashboardModel::waiter((int) $user['id']);
$s = db()->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND DATE(o.closed_at) = CURDATE() AND o.waiter_id = :u"); $s->execute([':u' => (int) $user['id']]); $t = $s->fetch();
$pageTitle = 'Dashboard'; $activeNav = 'dashboard'; $libs = []; $pageScripts = [];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="mb-3"><h4 class="fw-bold mb-0">Hi <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h4><div class="text-muted small"><?= date('l, d M Y') ?></div></div>
<div class="row g-3 mb-4">
    <?= stat_card('stat-indigo', 'bi-journal-text', count($d['mine']), 'My open orders') ?><?= stat_card('stat-green', 'bi-check2-circle', count($d['ready']), 'Dishes ready to serve') ?>
    <?= stat_card('stat-cyan', 'bi-grid-3x3-gap', $d['free_tables'], 'Free tables') ?><?= stat_card('stat-amber', 'bi-cash', $t['sales'], 'My sales today', 2, CURRENCY) ?>
</div>
<div class="row g-4">
    <div class="col-xl-5"><div class="card-panel h-100"><h6 class="fw-bold"><i class="bi bi-bell-fill text-success"></i> Ready to serve</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['ready'] as $r): ?><li><div><strong><?= (int) $r['qty'] ?>× <?= e($r['name']) ?></strong><div class="small text-muted"><?= $r['table_no'] ? 'Table ' . e($r['table_no']) : e($r['type']) ?> · waiting <?= (int) $r['waiting'] ?> min</div></div><a class="ms-auto btn btn-sm btn-success" href="<?= url('orders/' . (int) $r['order_id']) ?>">Serve</a></li><?php endforeach; ?><?php if (!$d['ready']): ?><li class="text-muted">Nothing waiting at the pass.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-4"><div class="card-panel h-100"><h6 class="fw-bold">My tables</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['mine'] as $o): ?><li><div><a class="fw-semibold" href="<?= url('orders/' . (int) $o['id']) ?>"><?= e($o['order_no']) ?></a> <span class="badge badge-soft-secondary"><?= e($o['table_no'] ?? '–') ?></span><div class="small text-muted"><?= (int) $o['guests'] ?> guests · <?= money($o['running']) ?></div></div><span class="ms-auto"><?= status_badge($o['status']) ?></span></li><?php endforeach; ?><?php if (!$d['mine']): ?><li class="text-muted">No open orders. <a href="<?= url('tables') ?>">Open a table</a>.</li><?php endif; ?></ul></div></div>
    <div class="col-xl-3"><div class="card-panel h-100"><h6 class="fw-bold">Reservations today</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($d['reservations'] as $r): ?><li><div><strong><?= e($r['time']) ?></strong> <?= e($r['guest_name']) ?><div class="small text-muted">×<?= (int) $r['party_size'] ?><?= $r['table_no'] ? ' · ' . e($r['table_no']) : '' ?></div></div></li><?php endforeach; ?><?php if (!$d['reservations']): ?><li class="text-muted">None.</li><?php endif; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
