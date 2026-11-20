<?php
declare(strict_types=1);

final class IngredientModel extends BaseModel
{
    protected static string $table = 'ingredients';
    protected static array $fields = [
        'name'          => ['label' => 'Ingredient', 'required' => true, 'max' => 80, 'unique' => true],
        'unit'          => ['label' => 'Unit', 'type' => 'enum', 'in' => ['kg', 'l', 'pcs'], 'required' => true],
        'reorder_level' => ['label' => 'Reorder level', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 1000000],
        'cost_per_unit' => ['label' => 'Cost per unit', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 1000000],
        'supplier_id'   => ['label' => 'Supplier', 'type' => 'ref', 'ref' => 'suppliers'],
        'is_active'     => ['label' => 'Active', 'type' => 'bool'],
        'opening_stock' => ['label' => 'Opening stock', 'type' => 'decimal', 'min' => 0, 'max' => 1000000, 'virtual' => true],
    ];
    protected static function baseSelect(): string
    {
        return "SELECT t.*, s.name AS supplier, (t.stock <= t.reorder_level) AS low,
            (SELECT COUNT(*) FROM recipes r WHERE r.ingredient_id = t.id) AS used_in FROM ingredients t LEFT JOIN suppliers s ON s.id = t.supplier_id";
    }
    protected static function orderBy(): string { return 't.name' === '' ? '' : 'ORDER BY (t.stock <= t.reorder_level) DESC, t.name'; }

    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        $open = (float) ($raw['opening_stock'] ?? 0);
        if ($isNew && $open > 0) {
            db()->prepare('UPDATE ingredients SET stock = :q WHERE id = :id')->execute([':q' => $open, ':id' => $id]);
            self::movement($id, 'Purchase', $open, (float) $d['cost_per_unit'], 'Opening stock', $d['supplier_id'] ?? null);
        }
        Audit::log($isNew ? 'create' : 'update', 'ingredient', $id, $d['name']);
    }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT (SELECT COUNT(*) FROM recipes WHERE ingredient_id = :a) + (SELECT COUNT(*) FROM stock_movements WHERE ingredient_id = :b)');
        $s->execute([':a' => $id, ':b' => $id]);
        return $s->fetchColumn() > 0 ? 'This ingredient is used in recipes or has stock history. Mark it inactive instead.' : null;
    }

    public static function movement(int $id, string $type, float $qty, ?float $cost, ?string $ref, mixed $supplier = null): void
    {
        db()->prepare('INSERT INTO stock_movements (ingredient_id, type, qty, unit_cost, reference, supplier_id, created_by) VALUES (:i,:t,:q,:c,:r,:s,:u)')
            ->execute([':i' => $id, ':t' => $type, ':q' => $qty, ':c' => $cost, ':r' => $ref !== null ? mb_substr($ref, 0, 80) : null, ':s' => $supplier ?: null, ':u' => Auth::id() ?: null]);
    }
    public static function movements(int $id, int $limit = 40): array
    {
        $s = db()->prepare("SELECT m.*, u.full_name AS user_name, s.name AS supplier FROM stock_movements m LEFT JOIN users u ON u.id = m.created_by LEFT JOIN suppliers s ON s.id = m.supplier_id
                            WHERE m.ingredient_id = :id ORDER BY m.id DESC LIMIT $limit");
        $s->execute([':id' => $id]);
        return $s->fetchAll();
    }
    public static function low(): array
    {
        return db()->query("SELECT id, name, unit, stock, reorder_level FROM ingredients WHERE is_active = 1 AND stock <= reorder_level ORDER BY (stock / NULLIF(reorder_level,0)), name")->fetchAll();
    }
    public static function options(): array
    {
        return db()->query('SELECT id, name, unit, cost_per_unit FROM ingredients WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    /** receive | wastage | adjust */
    public static function action(string $action, array $user, array $in): never
    {
        $id = (int) ($in['id'] ?? 0);
        $pdo = db(); $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT * FROM ingredients WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]); $ing = $s->fetch();
        $fail = function (string $m, int $c = 422) use ($pdo): never { $pdo->rollBack(); json_response(['success' => false, 'message' => $m], $c); };
        if (!$ing) { $fail('Ingredient not found.', 404); }
        $qty = (float) ($in['qty'] ?? 0);
        $note = trim((string) ($in['reference'] ?? ''));
        if ($action === 'receive') {
            if ($qty <= 0 || $qty > 1000000) { $fail('Enter a quantity above zero.'); }
            $cost = ($in['unit_cost'] ?? '') === '' ? (float) $ing['cost_per_unit'] : (float) $in['unit_cost'];
            if ($cost < 0) { $fail('Cost cannot be negative.'); }
            $sup = (int) ($in['supplier_id'] ?? 0) ?: ($ing['supplier_id'] ? (int) $ing['supplier_id'] : null);
            if ($sup) { $c = $pdo->prepare('SELECT 1 FROM suppliers WHERE id = :id'); $c->execute([':id' => $sup]); if (!$c->fetchColumn()) { $fail('Unknown supplier.'); } }
            $pdo->prepare('UPDATE ingredients SET stock = stock + :q, cost_per_unit = :c, supplier_id = COALESCE(:s, supplier_id) WHERE id = :id')->execute([':q' => $qty, ':c' => $cost, ':s' => $sup, ':id' => $id]);
            self::movement($id, 'Purchase', $qty, $cost, $note !== '' ? $note : 'Goods received', $sup);
            $msg = "Received $qty {$ing['unit']} of {$ing['name']}.";
        } elseif ($action === 'wastage') {
            if ($qty <= 0) { $fail('Enter a quantity above zero.'); }
            if ($qty > (float) $ing['stock']) { $fail('Only ' . (float) $ing['stock'] . ' ' . $ing['unit'] . ' in stock.'); }
            if ($note === '') { $fail('Give a reason (spoiled, dropped, expired…).'); }
            $pdo->prepare('UPDATE ingredients SET stock = stock - :q WHERE id = :id')->execute([':q' => $qty, ':id' => $id]);
            self::movement($id, 'Wastage', -$qty, null, $note);
            $msg = "Wrote off $qty {$ing['unit']} of {$ing['name']}.";
        } elseif ($action === 'adjust') {
            $new = (float) ($in['new_stock'] ?? -1);
            if (($in['new_stock'] ?? '') === '' || $new < 0) { $fail('Enter the counted stock (zero or more).'); }
            if ($note === '') { $fail('Give a reason for the stock-take adjustment.'); }
            $diff = round($new - (float) $ing['stock'], 3);
            if ($diff == 0.0) { $fail('That matches the current stock.'); }
            $pdo->prepare('UPDATE ingredients SET stock = :q WHERE id = :id')->execute([':q' => $new, ':id' => $id]);
            self::movement($id, 'Adjustment', $diff, null, $note);
            $msg = "Stock of {$ing['name']} adjusted to $new {$ing['unit']}.";
        } else { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Unknown action.'], 400); }
        $pdo->commit();
        Audit::log($action, 'ingredient', $id, $msg);
        json_response(['success' => true, 'message' => $msg]);
    }
}
