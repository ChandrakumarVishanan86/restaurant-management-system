<?php
declare(strict_types=1);

/** Kitchen display: live board and status transitions with recipe-based stock deduction. */
final class KitchenModel
{
    private const NEXT = ['New' => 'Preparing', 'Preparing' => 'Ready', 'Ready' => 'Served'];

    public static function board(): array
    {
        $rows = db()->query("SELECT i.id, i.order_id, i.name, i.qty, i.note, i.status, i.station, i.kot_no, i.sent_at, i.started_at, i.ready_at,
                TIMESTAMPDIFF(SECOND, COALESCE(i.started_at, i.sent_at), NOW()) AS age_sec, m.prep_minutes, m.icon,
                o.order_no, o.type, o.source, o.guests, o.note AS order_note, t.table_no, COALESCE(c.full_name, o.guest_name) AS customer, u.full_name AS waiter
            FROM order_items i JOIN orders o ON o.id = i.order_id JOIN menu_items m ON m.id = i.menu_item_id
            LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN customers c ON c.id = o.customer_id LEFT JOIN users u ON u.id = o.waiter_id
            WHERE i.status IN ('New','Preparing','Ready') AND o.status = 'Open' ORDER BY i.sent_at, i.id")->fetchAll();
        $groups = [];
        foreach ($rows as $r) {
            $k = $r['order_id'] . '-' . $r['kot_no'];
            if (!isset($groups[$k])) {
                $groups[$k] = ['key' => $k, 'order_id' => (int) $r['order_id'], 'order_no' => $r['order_no'], 'kot_no' => (int) $r['kot_no'], 'type' => $r['type'], 'source' => $r['source'],
                    'table_no' => $r['table_no'], 'customer' => $r['customer'], 'waiter' => $r['waiter'], 'guests' => (int) $r['guests'], 'order_note' => $r['order_note'], 'sent_at' => $r['sent_at'], 'items' => []];
            }
            $groups[$k]['items'][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'qty' => (int) $r['qty'], 'note' => $r['note'], 'status' => $r['status'], 'station' => $r['station'],
                'icon' => $r['icon'], 'age_sec' => (int) $r['age_sec'], 'prep_min' => (int) $r['prep_minutes']];
        }
        return array_values($groups);
    }
    public static function stats(): array
    {
        return db()->query("SELECT
            (SELECT COUNT(*) FROM order_items WHERE status = 'New') AS waiting,
            (SELECT COUNT(*) FROM order_items WHERE status = 'Preparing') AS cooking,
            (SELECT COUNT(*) FROM order_items WHERE status = 'Ready') AS ready,
            (SELECT COUNT(*) FROM order_items WHERE status = 'Served' AND DATE(served_at) = CURDATE()) AS served_today,
            (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, started_at, ready_at)), 1) FROM order_items WHERE ready_at IS NOT NULL AND DATE(ready_at) = CURDATE()) AS avg_prep")->fetch();
    }

    /** Advance items one step. $to: Preparing | Ready | Served. Returns [count, error]. */
    public static function advance(array $ids, string $to, array $user): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return [0, 'Nothing selected.']; }
        if (!in_array($to, ['Preparing', 'Ready', 'Served'], true)) { return [0, 'Invalid status.']; }
        $roles = $to === 'Served' ? ['admin', 'waiter', 'cashier'] : ['admin', 'chef'];
        if (!in_array($user['role'], $roles, true)) { return [0, 'Your role cannot do that.']; }
        $from = array_search($to, self::NEXT, true);
        $pdo = db(); $pdo->beginTransaction();
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT i.*, o.status AS order_status, o.order_no FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.id IN ($ph) ORDER BY i.id FOR UPDATE");
            $s->execute($ids); $items = $s->fetchAll();
            if (count($items) !== count($ids)) { throw new RuntimeException('Some items no longer exist.'); }
            foreach ($items as $it) {
                if ($it['order_status'] !== 'Open') { throw new RuntimeException('Order ' . $it['order_no'] . ' is not open.'); }
                if ($it['status'] !== $from) { throw new RuntimeException($it['name'] . ' is already ' . $it['status'] . '. Refresh the board.'); }
            }
            if ($to === 'Preparing') {
                $need = []; $ref = [];
                foreach ($items as $it) {
                    $r = $pdo->prepare('SELECT ingredient_id, qty FROM recipes WHERE menu_item_id = :m'); $r->execute([':m' => $it['menu_item_id']]);
                    foreach ($r->fetchAll() as $l) { $need[$l['ingredient_id']] = ($need[$l['ingredient_id']] ?? 0) + (float) $l['qty'] * (int) $it['qty']; $ref[$l['ingredient_id']] = $it['order_no']; }
                }
                ksort($need);
                foreach ($need as $ingId => $qty) {
                    $g = $pdo->prepare('SELECT name, unit, stock FROM ingredients WHERE id = :id FOR UPDATE'); $g->execute([':id' => $ingId]); $ing = $g->fetch();
                    if ((float) $ing['stock'] + 1e-9 < $qty) { throw new RuntimeException('Not enough ' . $ing['name'] . ' (have ' . (float) $ing['stock'] . ' ' . $ing['unit'] . ', need ' . round($qty, 3) . '). Restock or mark the dish sold out.'); }
                    $pdo->prepare('UPDATE ingredients SET stock = stock - :q WHERE id = :id')->execute([':q' => round($qty, 3), ':id' => $ingId]);
                    IngredientModel::movement((int) $ingId, 'Usage', -round($qty, 3), null, 'Cooked for ' . $ref[$ingId]);
                }
            }
            $col = ['Preparing' => 'started_at', 'Ready' => 'ready_at', 'Served' => 'served_at'][$to];
            $extra = $to === 'Ready' ? ', started_at = COALESCE(started_at, NOW())' : '';
            $pdo->prepare("UPDATE order_items SET status = ?, $col = NOW()$extra WHERE id IN ($ph)")->execute(array_merge([$to], $ids));
            $pdo->commit();
            return [count($ids), null];
        } catch (RuntimeException $e) { $pdo->rollBack(); return [0, $e->getMessage()]; }
    }
}
