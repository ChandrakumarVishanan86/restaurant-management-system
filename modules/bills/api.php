<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'cashier', 'customer'], json: true);
$fail = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $a = $_GET['action'] ?? '';
    if ($a === 'list') { json_response(['success' => true, 'data' => BillModel::list($user, (string) ($_GET['status'] ?? ''))]); }
    if ($a === 'billable') { if ($user['role'] === 'customer') { $fail('Forbidden.', 403); } json_response(['success' => true, 'data' => BillModel::billable()]); }
    $fail('Unknown action.', 400);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('Method not allowed.', 405); }
if ($user['role'] === 'customer') { $fail('Forbidden.', 403); }
if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
require_csrf();
$in = $_POST; $a = (string) ($in['action'] ?? ''); $id = (int) ($in['id'] ?? 0);
if (!BillModel::find($id)) { $fail('Bill not found.', 404); }
if ($a === 'pay') { $err = BillModel::addPayment($id, (float) ($in['amount'] ?? 0), (string) ($in['method'] ?? ''), trim((string) ($in['reference'] ?? '')) ?: null, $user); $err ? $fail($err) : json_response(['success' => true, 'message' => 'Payment recorded.']); }
if ($a === 'reopen') { $err = BillModel::reopen($id, $user); $err ? $fail($err) : json_response(['success' => true, 'message' => 'Bill cancelled, order reopened.']); }
$fail('Unknown action.', 400);
