<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'chef', 'waiter', 'cashier']);
$pageTitle = 'Kitchen Display'; $activeNav = 'kitchen'; $libs = []; $pageScripts = ['assets/js/kitchen.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="btn-group" id="stationF"><button class="btn btn-sm btn-soft active" data-s="">All</button><button class="btn btn-sm btn-soft" data-s="Kitchen">Kitchen</button><button class="btn btn-sm btn-soft" data-s="Bar">Bar</button></div>
    <div class="d-flex gap-3 small ms-auto" id="kStats"></div><span class="small text-muted"><i class="bi bi-arrow-repeat"></i> <span id="kNow">–</span></span>
</div>
<div class="kds" id="kds"></div>
<?php ob_start(); ?><script>window.KDS = { role: <?= json_encode($user['role']) ?> };</script><?php $pageConfig = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
