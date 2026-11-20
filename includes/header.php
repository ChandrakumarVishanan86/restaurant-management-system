<?php
/**
 * Shared header + sidebar. Pages may set before including:
 *   $pageTitle, $activeNav, $libs ['datatables','flatpickr','chart']
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$user      = Auth::guard();
$role      = $user['role'];
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$libs      = $libs ?? [];

// small counters for the sidebar
$badges = [];
if ($role !== 'customer') {
    $c = db()->query("SELECT
        (SELECT COUNT(*) FROM order_items WHERE status IN ('New','Preparing')) AS kitchen,
        (SELECT COUNT(*) FROM orders WHERE status IN ('Open','Billed')) AS orders,
        (SELECT COUNT(*) FROM bills WHERE status IN ('Unpaid','Partial')) AS bills,
        (SELECT COUNT(*) FROM reservations WHERE res_date = CURDATE() AND status = 'Booked') AS res,
        (SELECT COUNT(*) FROM ingredients WHERE is_active = 1 AND stock <= reorder_level) AS stock")->fetch();
    $badges = ['kitchen' => (int) $c['kitchen'], 'orders' => (int) $c['orders'], 'bills' => (int) $c['bills'], 'reservations' => (int) $c['res'], 'inventory' => (int) $c['stock']];
    if ($role === 'waiter') {   // waiters care about food that is ready to be served
        $badges['kitchen'] = (int) db()->query("SELECT COUNT(*) FROM order_items WHERE status = 'Ready'")->fetchColumn();
    }
}
$ALL = Auth::STAFF; $FL = Auth::FLOOR;
$mine = $role === 'customer';
// [key,label,icon,href,roles]
$menu = [
    ['Overview', [
        ['dashboard', 'Dashboard', 'bi-speedometer2', 'dashboard', array_merge($ALL, ['customer'])],
    ]],
    ['Service', [
        ['order-online', 'Order Online', 'bi-bag-heart-fill', 'order-online', ['customer']],
        ['tables', 'Tables', 'bi-grid-3x3-gap-fill', 'tables', $FL],
        ['orders', $mine ? 'My Orders' : 'Orders', 'bi-receipt-cutoff', 'orders', array_merge($FL, ['customer'])],
        ['kitchen', 'Kitchen Display', 'bi-fire', 'kitchen', ['admin', 'chef', 'waiter']],
        ['reservations', $mine ? 'Book a Table' : 'Reservations', 'bi-calendar2-heart-fill', 'reservations', array_merge($FL, ['customer'])],
    ]],
    ['Money', [
        ['bills', $mine ? 'My Bills' : 'Bills & Payments', 'bi-cash-stack', 'bills', ['admin', 'cashier', 'customer']],
        ['customers', 'Customers', 'bi-people-fill', 'customers', $FL],
        ['reports', 'Reports', 'bi-graph-up-arrow', 'reports', ['admin', 'cashier']],
    ]],
    ['Kitchen & stock', [
        ['menu', 'Menu & Recipes', 'bi-book-half', 'menu', ['admin', 'chef']],
        ['inventory', 'Inventory', 'bi-box-seam-fill', 'inventory', ['admin', 'chef']],
        ['suppliers', 'Suppliers', 'bi-truck', 'suppliers', ['admin', 'chef']],
    ]],
    ['Administration', [
        ['users', 'Staff Accounts', 'bi-person-gear', 'users', ['admin']],
        ['audit', 'Audit Log', 'bi-journal-text', 'audit', ['admin']],
    ]],
];
$initials = strtoupper(mb_substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php if (in_array('datatables', $libs, true)): ?>
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?php endif; ?>
<?php if (in_array('flatpickr', $libs, true)): ?>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
<?php endif; ?>
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-cup-hot-fill"></i></span>
            <span class="brand-text"><?= e(APP_NAME) ?></span>
            <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
        </div>

        <nav class="sidebar-nav">
<?php foreach ($menu as [$caption, $items]):
        $visible = array_filter($items, static fn($i) => in_array($role, $i[4], true));
        if (!$visible) continue; ?>
            <span class="nav-caption"><?= e($caption) ?></span>
<?php foreach ($visible as [$key, $label, $icon, $href]): ?>
            <a href="<?= url($href) ?>" class="side-link <?= $activeNav === $key ? 'active' : '' ?>">
                <i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span>
<?php if (!empty($badges[$key])): ?><span class="side-badge"><?= (int) $badges[$key] ?></span><?php endif; ?>
            </a>
<?php endforeach; endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <span class="avatar"><?= e($initials) ?></span>
                <div class="lh-sm">
                    <div class="fw-semibold text-truncate" style="max-width:130px"><?= e($user['full_name']) ?></div>
                    <small class="text-capitalize opacity-75"><?= e($role) ?></small>
                </div>
            </div>
            <a href="<?= url('logout') ?>" class="btn btn-sm btn-outline-light w-100 mt-3" data-confirm="Sign out of your account?">
                <i class="bi bi-box-arrow-right me-1"></i> Sign out
            </a>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
            <div>
                <h1 class="page-title mb-0"><?= e($pageTitle) ?></h1>
                <small class="text-muted d-none d-sm-block"><?= date('l, d F Y') ?></small>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
<?php if (in_array($role, Auth::FLOOR, true)): ?>
                <a href="<?= url('tables') ?>" class="btn btn-gradient btn-sm d-none d-sm-inline-flex align-items-center"><i class="bi bi-plus-circle-fill me-1"></i> New Order</a>
<?php elseif ($role === 'customer'): ?>
                <a href="<?= url('order-online') ?>" class="btn btn-gradient btn-sm d-none d-sm-inline-flex align-items-center"><i class="bi bi-bag-heart me-1"></i> Order Online</a>
<?php endif; ?>
                <span class="badge role-badge role-<?= e($role) ?>"><?= e(ucfirst($role)) ?></span>
            </div>
        </header>

        <main class="content fade-in">
<?php foreach (pull_flash() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
<?php endforeach; ?>
