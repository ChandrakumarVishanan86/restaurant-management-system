<?php
declare(strict_types=1);

final class TableModel extends BaseModel
{
    protected static string $table = 'dining_tables';
    protected static array $fields = [
        'table_no'  => ['label' => 'Table number', 'required' => true, 'max' => 10, 'upper' => true, 'unique' => true, 'regex' => '/^[A-Z0-9\-]{1,10}$/', 'regexMsg' => 'Use letters, numbers or - only.'],
        'area'      => ['label' => 'Area', 'type' => 'enum', 'in' => ['Indoor', 'Outdoor', 'Rooftop', 'Private'], 'required' => true],
        'seats'     => ['label' => 'Seats', 'type' => 'int', 'required' => true, 'min' => 1, 'max' => 30],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function orderBy(): string { return 'ORDER BY FIELD(t.area,\'Indoor\',\'Outdoor\',\'Rooftop\',\'Private\'), t.table_no'; }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        Audit::log($isNew ? 'create' : 'update', 'table', $id, 'Table ' . $d['table_no']);
    }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare("SELECT COUNT(*) FROM orders WHERE table_id = :id AND status IN ('Open','Billed')"); $s->execute([':id' => $id]);
        return $s->fetchColumn() > 0 ? 'This table has a running order.' : null;
    }

    /** Floor plan: every active table with its live order and next reservation today. */
    public static function floor(): array
    {
        $rows = db()->query("SELECT t.id, t.table_no, t.area, t.seats, t.status,
              o.id AS order_id, o.order_no, o.guests, o.created_at AS opened_at, o.status AS order_status, u.full_name AS waiter,
              (SELECT COALESCE(SUM(i.unit_price * i.qty), 0) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS running,
              (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.status = 'Ready') AS ready_items,
              (SELECT CONCAT(TIME_FORMAT(r.res_time,'%H:%i'),' · ',r.guest_name) FROM reservations r WHERE r.table_id = t.id AND r.res_date = CURDATE() AND r.status = 'Booked' ORDER BY r.res_time LIMIT 1) AS next_res
            FROM dining_tables t
            LEFT JOIN orders o ON o.table_id = t.id AND o.status IN ('Open','Billed')
            LEFT JOIN users u ON u.id = o.waiter_id
            WHERE t.is_active = 1 ORDER BY FIELD(t.area,'Indoor','Outdoor','Rooftop','Private'), t.table_no")->fetchAll();
        return $rows;
    }

    public static function action(string $action, array $user, array $in): never
    {
        if (!in_array($user['role'], Auth::FLOOR, true)) { json_response(['success' => false, 'message' => 'Not allowed.'], 403); }
        $id = (int) ($in['id'] ?? 0);
        if ($action === 'set_status') {
            $to = (string) ($in['status'] ?? '');
            if (!in_array($to, ['Available', 'Cleaning'], true)) { json_response(['success' => false, 'message' => 'Choose Available or Cleaning.'], 422); }
            $pdo = db(); $pdo->beginTransaction();
            $s = $pdo->prepare('SELECT * FROM dining_tables WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]); $t = $s->fetch();
            if (!$t) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Table not found.'], 404); }
            $o = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE table_id = :id AND status IN ('Open','Billed')"); $o->execute([':id' => $id]);
            if ($o->fetchColumn() > 0) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'This table has a running order.'], 409); }
            $pdo->prepare('UPDATE dining_tables SET status = :s WHERE id = :id')->execute([':s' => $to, ':id' => $id]);
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Table ' . $t['table_no'] . ' is now ' . $to . '.']);
        }
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
    }
}
