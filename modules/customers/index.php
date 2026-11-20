<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::FLOOR);
$pageTitle = 'Customers'; $activeNav = 'customers'; $libs = ['datatables', 'flatpickr']; $pageScripts = ['assets/js/crud.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$isAdmin = $user['role'] === 'admin';
?>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Customers &amp; loyalty</h5><small class="text-muted">1 point for every <?= e(CURRENCY) ?> <?= POINTS_PER_RS ?> spent; 1 point = <?= e(CURRENCY) ?> <?= POINT_VALUE ?> off.</small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-person-plus-fill me-1"></i> Add Customer</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Customer</th><th>Phone</th><th class="text-center">Visits</th><th class="text-end">Total spent</th><th class="text-center">Points</th><th>Last visit</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
<script>
new Crud({ table: '#tbl', api: APP.base + '/customers/api', label: 'Customer', canWrite: true, canDelete: <?= $isAdmin ? 'true' : 'false' ?>, order: [[3, 'desc']], rowName: function (r) { return r.full_name; },
    columns: [{ data: 'full_name', render: function (d, t, r) { return '<a class="fw-semibold" href="' + APP.base + '/customers/' + r.id + '">' + SMS.esc(d) + '</a><div class="small text-muted">' + SMS.esc(r.code) + '</div>'; } },
        { data: 'phone' }, { data: 'visits', className: 'text-center' }, { data: 'total_spent', className: 'text-end', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } },
        { data: 'loyalty_points', className: 'text-center', render: function (d) { return '<span class="badge badge-soft-warning"><i class="bi bi-star-fill"></i> ' + d + '</span>'; } },
        { data: 'last_visit', render: function (d) { return d ? SMS.fdate(d) : '–'; } }],
    fields: [{ name: 'full_name', label: 'Full name', required: true, max: 120, col: 6, pattern: "^[A-Za-z\\u00C0-\\u024F][A-Za-z\\u00C0-\\u024F .'\\-]*$", patternMsg: 'Name may contain letters only.' },
        { name: 'phone', label: 'Phone', type: 'tel', required: true, max: 20, col: 6, unique: true, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
        { name: 'email', label: 'Email', type: 'email', max: 120, col: 6 }, { name: 'birthday', label: 'Birthday', type: 'date', col: 6 },
        { name: 'address', label: 'Address', max: 255, col: 12 }, { name: 'notes', label: 'Notes / preferences', max: 255, col: 12, placeholder: 'Allergies, favourite table…' },
        { name: 'is_active', label: 'Active', type: 'checkbox', default: true, col: 6 },
        { name: 'create_login', label: 'Create online-ordering login', type: 'checkbox', createOnly: true, col: 6, help: 'Username c + phone digits, password customer123.' }] });
</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
