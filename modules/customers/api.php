<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::FLOOR, json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search') {
    json_response(['success' => true, 'data' => CustomerModel::search(trim((string) ($_GET['q'] ?? '')))]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? (json_input()['action'] ?? '');
    if ($act === 'delete' && $user['role'] !== 'admin') { json_response(['success' => false, 'message' => 'Only the manager can delete customers.'], 403); }
}
CrudController::handle(CustomerModel::class, ['read' => Auth::FLOOR, 'write' => Auth::FLOOR, 'label' => 'Customer']);
