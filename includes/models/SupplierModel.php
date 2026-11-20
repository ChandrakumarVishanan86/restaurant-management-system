<?php
declare(strict_types=1);

final class SupplierModel extends BaseModel
{
    protected static string $table = 'suppliers';
    protected static array $fields = [
        'name'    => ['label' => 'Supplier name', 'required' => true, 'max' => 100, 'unique' => true],
        'phone'   => ['label' => 'Phone', 'max' => 20, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'email'   => ['label' => 'Email', 'type' => 'email', 'max' => 120, 'lower' => true],
        'address' => ['label' => 'Address', 'max' => 255],
    ];
    protected static function baseSelect(): string
    {
        return 'SELECT t.*, (SELECT COUNT(*) FROM ingredients i WHERE i.supplier_id = t.id) AS items FROM suppliers t';
    }
    protected static function orderBy(): string { return 'ORDER BY t.name'; }
}
