<?php
declare(strict_types=1);

final class MenuItemModel extends BaseModel
{
    protected static string $table = 'menu_items';
    protected static array $fields = [
        'category_id' => ['label' => 'Category', 'type' => 'ref', 'ref' => 'categories', 'required' => true],
        'name'        => ['label' => 'Item name', 'required' => true, 'max' => 100],
        'description' => ['label' => 'Description', 'max' => 255],
        'price'       => ['label' => 'Price', 'type' => 'decimal', 'required' => true, 'min' => 1, 'max' => 999999],
        'is_veg'      => ['label' => 'Vegetarian', 'type' => 'bool'],
        'spice_level' => ['label' => 'Spice level', 'type' => 'int', 'min' => 0, 'max' => 3, 'default' => 0],
        'prep_minutes' => ['label' => 'Prep time (min)', 'type' => 'int', 'required' => true, 'min' => 1, 'max' => 180],
        'icon'        => ['label' => 'Emoji', 'max' => 8],
        'is_available' => ['label' => 'Available today', 'type' => 'bool'],
        'is_active'   => ['label' => 'On menu', 'type' => 'bool'],
    ];

    protected static function baseSelect(): string
    {
        return "SELECT t.*, c.name AS category, c.station,
            (SELECT COALESCE(SUM(r.qty * i.cost_per_unit), 0) FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id WHERE r.menu_item_id = t.id) AS cost,
            (SELECT COUNT(*) FROM recipes r WHERE r.menu_item_id = t.id) AS ingredients,
            (SELECT COALESCE(SUM(oi.qty), 0) FROM order_items oi WHERE oi.menu_item_id = t.id AND oi.status <> 'Cancelled') AS sold
            FROM menu_items t JOIN categories c ON c.id = t.category_id";
    }
    protected static function orderBy(): string { return 'ORDER BY c.sort_order, t.name'; }

    public static function validate(array $d, int $id = 0): array
    {
        $err = parent::validate($d, $id);
        if (!isset($err['name']) && !empty($d['category_id'])) {
            $s = db()->prepare('SELECT 1 FROM menu_items WHERE category_id = :c AND name = :n AND id <> :id');
            $s->execute([':c' => $d['category_id'], ':n' => $d['name'], ':id' => $id]);
            if ($s->fetchColumn()) { $err['name'] = 'This item already exists in that category.'; }
        }
        return $err;
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        Audit::log($isNew ? 'create' : 'update', 'menu_item', $id, $d['name'] . ' @ ' . $d['price']);
    }

    /** Orderable items grouped for the POS / customer menu. */
    public static function orderable(): array
    {
        return db()->query("SELECT t.id, t.category_id, t.name, t.description, t.price, t.is_veg, t.spice_level, t.prep_minutes, t.icon, t.is_available, c.name AS category, c.station
            FROM menu_items t JOIN categories c ON c.id = t.category_id WHERE t.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_order, t.name")->fetchAll();
    }
    public static function categories(): array
    {
        return db()->query('SELECT id, name, station FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    }

    public static function recipe(int $id): array
    {
        $s = db()->prepare('SELECT r.ingredient_id, r.qty, i.name, i.unit, i.cost_per_unit FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id WHERE r.menu_item_id = :id ORDER BY i.name');
        $s->execute([':id' => $id]);
        return $s->fetchAll();
    }

    /** Custom POST actions: toggle availability, save recipe. */
    public static function action(string $action, array $user, array $in): never
    {
        $id = (int) ($in['id'] ?? 0);
        $row = static::find($id);
        if (!$row) { json_response(['success' => false, 'message' => 'Menu item not found.'], 404); }
        if ($action === 'toggle') {
            $v = !empty($in['available']) && $in['available'] !== '0' && $in['available'] !== 'false' ? 1 : 0;
            db()->prepare('UPDATE menu_items SET is_available = :v WHERE id = :id')->execute([':v' => $v, ':id' => $id]);
            Audit::log('availability', 'menu_item', $id, $row['name'] . ($v ? ' back on' : ' marked sold out'));
            json_response(['success' => true, 'message' => $row['name'] . ($v ? ' is available again.' : ' marked as sold out.')]);
        }
        if ($action === 'save_recipe') {
            $lines = []; $seen = [];
            foreach ((array) ($in['lines'] ?? []) as $l) {
                $ing = (int) ($l['ingredient_id'] ?? 0); $qty = (float) ($l['qty'] ?? 0);
                if ($ing <= 0 || $qty <= 0 || $qty > 100000) { json_response(['success' => false, 'message' => 'Every recipe line needs an ingredient and a quantity above zero.'], 422); }
                if (isset($seen[$ing])) { json_response(['success' => false, 'message' => 'An ingredient can appear only once in a recipe.'], 422); }
                $seen[$ing] = true; $lines[] = [$ing, round($qty, 3)];
            }
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($lines) {
                    $in_ = implode(',', array_fill(0, count($lines), '?'));
                    $c = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE id IN ($in_)"); $c->execute(array_column($lines, 0));
                    if ((int) $c->fetchColumn() !== count($lines)) { throw new RuntimeException('Unknown ingredient in recipe.'); }
                }
                $pdo->prepare('DELETE FROM recipes WHERE menu_item_id = :id')->execute([':id' => $id]);
                $ins = $pdo->prepare('INSERT INTO recipes (menu_item_id, ingredient_id, qty) VALUES (:m,:i,:q)');
                foreach ($lines as [$ing, $qty]) { $ins->execute([':m' => $id, ':i' => $ing, ':q' => $qty]); }
                $pdo->commit();
            } catch (RuntimeException $e) {
                $pdo->rollBack(); json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            Audit::log('recipe', 'menu_item', $id, $row['name'] . ': ' . count($lines) . ' ingredient(s)');
            json_response(['success' => true, 'message' => 'Recipe saved.']);
        }
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
    }
}
