<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::FLOOR, json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'floor') {
    json_response(['success' => true, 'data' => TableModel::floor(), 'now' => date('Y-m-d H:i:s')]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? (json_input()['action'] ?? '');
    if (in_array($act, ['save', 'delete'], true) && $user['role'] !== 'admin') { json_response(['success' => false, 'message' => 'Only the manager can change tables.'], 403); }
}
CrudController::handle(TableModel::class, ['read' => Auth::FLOOR, 'write' => Auth::FLOOR, 'label' => 'Table']);
