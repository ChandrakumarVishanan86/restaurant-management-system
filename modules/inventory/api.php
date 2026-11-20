<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin', 'chef'], json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'movements') {
    json_response(['success' => true, 'data' => IngredientModel::movements((int) ($_GET['id'] ?? 0))]);
}
CrudController::handle(IngredientModel::class, ['read' => ['admin', 'chef'], 'write' => ['admin', 'chef'], 'label' => 'Ingredient']);
