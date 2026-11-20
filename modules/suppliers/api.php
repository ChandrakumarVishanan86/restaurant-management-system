<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
CrudController::handle(SupplierModel::class, ['read' => ['admin', 'chef'], 'write' => ['admin', 'chef'], 'label' => 'Supplier']);
