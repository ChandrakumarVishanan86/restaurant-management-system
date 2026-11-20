<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Staff Accounts'; $activeNav = 'users'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$roles = array_map(static fn($r) => [$r, ucfirst($r)], UserModel::ROLES);
?>
<div class="card-panel">
    <div class="d-flex align-items-center mb-3"><div><h5 class="mb-0 fw-bold">Staff Accounts</h5><small class="text-muted">Admins, waiters, chefs and cashiers. Customer logins are created from the Customers page.</small></div>
        <button class="btn btn-gradient ms-auto" id="btnAdd"><i class="bi bi-person-plus-fill me-1"></i> Add Staff</button></div>
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Phone</th><th>Last login</th><th>Status</th><th class="text-end">Actions</th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
<script>
new Crud({ table: '#tbl', api: APP.base + '/users/api', label: 'Staff account', canWrite: true, rowName: function (r) { return r.full_name; },
    columns: [{ data: 'full_name', render: function (d) { return '<span class="fw-semibold">' + SMS.esc(d) + '</span>'; } }, { data: 'username' },
        { data: 'role', render: function (d) { return '<span class="badge role-' + d + '">' + SMS.esc(d) + '</span>'; } }, { data: 'phone', render: function (d) { return d ? SMS.esc(d) : '–'; } },
        { data: 'last_login', render: function (d) { return d ? SMS.fdatetime(d) : '<span class="text-muted">never</span>'; } },
        { data: 'is_active', render: function (d) { return parseInt(d, 10) ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
    fields: [{ name: 'full_name', label: 'Full name', required: true, max: 120, col: 6 }, { name: 'username', label: 'Username', required: true, max: 50, col: 6, unique: true, pattern: '^[A-Za-z0-9._\\-]{3,50}$', patternMsg: '3-50 chars: a-z, 0-9 . _ -' },
        { name: 'email', label: 'Email', type: 'email', max: 120, col: 6, unique: true }, { name: 'phone', label: 'Phone', type: 'tel', max: 20, col: 6, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
        { name: 'role', label: 'Role', type: 'select', required: true, col: 6, options: <?= json_encode($roles) ?> },
        { name: 'password', label: 'Password', type: 'password', col: 6, max: 72, minlen: 8, help: 'Min 8 characters. Leave blank when editing to keep the current password.' },
        { name: 'is_active', label: 'Active', type: 'checkbox', col: 12, default: true }] });
</script>
<?php $pageInline = ob_get_clean(); require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
