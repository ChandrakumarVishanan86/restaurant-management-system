<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin']);
$rows = db()->query("SELECT a.*, u.full_name, u.role FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 1000")->fetchAll();
$pageTitle = 'Audit Log'; $activeNav = 'audit'; $libs = ['datatables']; $pageScripts = [];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel"><h5 class="fw-bold mb-3">Audit log <small class="text-muted fw-normal">latest 1000 events</small></h5>
<div class="table-responsive"><table class="table table-hover table-sm align-middle w-100" id="tbl"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-order="<?= e($r['created_at']) ?>"><?= fdatetime($r['created_at']) ?></td><td><?= e($r['full_name'] ?? 'system') ?> <small class="text-muted"><?= e((string) $r['role']) ?></small></td><td><span class="badge badge-soft-secondary"><?= e($r['action']) ?></span></td><td><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td><td class="small"><?= e((string) $r['details']) ?></td><td class="small text-muted"><?= e((string) $r['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php ob_start(); ?><script>$('#tbl').DataTable({ order: [[0, 'desc']], pageLength: 25, language: { search: '', searchPlaceholder: 'Search…' } });</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
