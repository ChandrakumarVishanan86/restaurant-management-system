<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'chef', 'waiter', 'cashier'], json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['success' => true, 'data' => KitchenModel::board(), 'stats' => KitchenModel::stats(), 'now' => date('H:i:s')]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Method not allowed.'], 405); }
if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
require_csrf();
if (($_POST['action'] ?? '') !== 'advance') { json_response(['success' => false, 'message' => 'Unknown action.'], 400); }
[$n, $err] = KitchenModel::advance((array) ($_POST['ids'] ?? []), (string) ($_POST['to'] ?? ''), $user);
$err ? json_response(['success' => false, 'message' => $err], 422) : json_response(['success' => true, 'message' => "$n item(s) updated."]);
