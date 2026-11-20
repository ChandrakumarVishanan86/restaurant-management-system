<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
CrudController::handle(UserModel::class, ['read' => ['admin'], 'write' => ['admin'], 'label' => 'Staff account']);
