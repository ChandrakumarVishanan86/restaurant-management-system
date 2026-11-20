<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['customer'], json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET') { json_response(['success' => true, 'data' => MenuItemModel::orderable()]); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Method not allowed.'], 405); }
if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
require_csrf();
[$id, $err] = OrderModel::placeOnline($user, $_POST);
$err ? json_response(['success' => false, 'message' => $err], 422) : json_response(['success' => true, 'id' => $id, 'message' => 'Order placed! The kitchen has it.']);
