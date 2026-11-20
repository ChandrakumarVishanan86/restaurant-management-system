<?php
declare(strict_types=1);

final class CategoryModel extends BaseModel
{
    protected static string $table = 'categories';
    protected static array $fields = [
        'name'       => ['label' => 'Category name', 'required' => true, 'max' => 60, 'unique' => true],
        'station'    => ['label' => 'Station', 'type' => 'enum', 'in' => ['Kitchen', 'Bar'], 'required' => true],
        'sort_order' => ['label' => 'Sort order', 'type' => 'int', 'min' => 0, 'max' => 999, 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function baseSelect(): string
    {
        return 'SELECT t.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = t.id) AS items FROM categories t';
    }
    protected static function orderBy(): string { return 'ORDER BY t.sort_order, t.name'; }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        Audit::log($isNew ? 'create' : 'update', 'category', $id, $d['name']);
    }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id = :id'); $s->execute([':id' => $id]);
        return $s->fetchColumn() > 0 ? 'This category still has menu items. Move or delete them first.' : null;
    }
}
