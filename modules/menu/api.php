<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin', 'chef'], json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'recipe') {
    json_response(['success' => true, 'data' => MenuItemModel::recipe((int) ($_GET['id'] ?? 0)), 'ingredients' => IngredientModel::options()]);
}
CrudController::handle(MenuItemModel::class, ['read' => ['admin', 'chef'], 'write' => ['admin', 'chef'], 'label' => 'Menu item']);
