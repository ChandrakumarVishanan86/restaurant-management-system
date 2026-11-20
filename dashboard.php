<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = Auth::guard();
$allowed = ['admin', 'waiter', 'chef', 'cashier', 'customer'];
if (!in_array($user['role'], $allowed, true)) { http_response_code(403); exit('No dashboard for this role.'); }
function stat_card(string $cls, string $icon, $num, string $label, int $dec = 0, string $prefix = ''): string
{
    return '<div class="col-sm-6 col-xl-3"><div class="stat-card ' . $cls . ' hover-lift"><span class="stat-icon"><i class="bi ' . $icon . '"></i></span><div><div class="stat-num"' . ($prefix === '' ? ' data-count="' . (float) $num . '" data-decimals="' . $dec . '">0' : '>' . e($prefix) . ' ' . number_format((float) $num, $dec)) . '</div><div class="small opacity-75">' . e($label) . '</div></div></div></div>';
}
require __DIR__ . '/modules/dashboard/' . $user['role'] . '.php';
