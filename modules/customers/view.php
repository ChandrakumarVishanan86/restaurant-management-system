<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::FLOOR);
$c = CustomerModel::find((int) ($_GET['id'] ?? 0));
if (!$c) { http_response_code(404); exit('Customer not found.'); }
$cid = (int) $c['id'];
$q = static function (string $sql) use ($cid): array { $s = db()->prepare($sql); $s->execute([':c' => $cid]); return $s->fetchAll(); };
$orders = $q("SELECT o.id, o.order_no, o.type, o.status, o.created_at, b.total FROM orders o LEFT JOIN bills b ON b.order_id = o.id WHERE o.customer_id = :c ORDER BY o.id DESC LIMIT 25");
$res = $q("SELECT r.*, t.table_no FROM reservations r LEFT JOIN dining_tables t ON t.id = r.table_id WHERE r.customer_id = :c ORDER BY r.res_date DESC LIMIT 10");
$fav = CustomerModel::favourites($cid);
$pageTitle = $c['full_name']; $activeNav = 'customers'; $libs = []; $pageScripts = [];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel mb-4"><div class="d-flex flex-wrap gap-3 align-items-start">
    <span class="avatar avatar-lg bg-primary"><?= e(mb_strtoupper(mb_substr($c['full_name'], 0, 1))) ?></span>
    <div><h4 class="fw-bold mb-1"><?= e($c['full_name']) ?></h4><div class="text-muted"><span class="plate"><?= e($c['code']) ?></span> · <i class="bi bi-telephone"></i> <?= e($c['phone']) ?><?= $c['email'] ? ' · ' . e($c['email']) : '' ?></div>
        <?php if ($c['address']): ?><div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($c['address']) ?></div><?php endif; ?><?php if ($c['notes']): ?><div class="small mt-1"><i class="bi bi-chat-left-text"></i> <?= e($c['notes']) ?></div><?php endif; ?></div>
    <div class="ms-auto d-flex gap-4 text-center"><div><div class="fs-4 fw-bold"><?= (int) $c['visits'] ?></div><div class="small text-muted">Visits</div></div><div><div class="fs-4 fw-bold"><?= money($c['total_spent']) ?></div><div class="small text-muted">Spent</div></div>
        <div><div class="fs-4 fw-bold text-warning"><i class="bi bi-star-fill"></i> <?= (int) $c['loyalty_points'] ?></div><div class="small text-muted">Points</div></div></div></div></div>
<div class="row g-4">
    <div class="col-xl-7"><div class="card-panel"><h6 class="fw-bold">Order history</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Order</th><th>Date</th><th>Type</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($orders as $o): ?><tr><td><a href="<?= url('orders/' . (int) $o['id']) ?>"><span class="plate"><?= e($o['order_no']) ?></span></a></td><td><?= fdatetime($o['created_at']) ?></td><td><?= e($o['type']) ?></td><td class="text-end"><?= $o['total'] !== null ? money($o['total']) : '–' ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="5" class="text-center text-muted py-3">No orders yet.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="col-xl-5"><div class="card-panel mb-4"><h6 class="fw-bold">Favourites</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($fav as $f): ?><li><strong><?= e($f['name']) ?></strong><span class="ms-auto badge badge-soft-secondary">× <?= (int) $f['qty'] ?></span></li><?php endforeach; ?><?php if (!$fav): ?><li class="text-muted">No data yet.</li><?php endif; ?></ul></div>
        <div class="card-panel"><h6 class="fw-bold">Reservations</h6><ul class="list-unstyled mini-list mb-0"><?php foreach ($res as $r): ?><li><div><?= fdate($r['res_date']) ?> <?= e(substr((string) $r['res_time'], 0, 5)) ?><div class="small text-muted">Party of <?= (int) $r['party_size'] ?><?= $r['table_no'] ? ' · ' . e($r['table_no']) : '' ?></div></div><span class="ms-auto"><?= status_badge($r['status']) ?></span></li><?php endforeach; ?><?php if (!$res): ?><li class="text-muted">None.</li><?php endif; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
