<?php
declare(strict_types=1);

final class DashboardModel
{
    private static function rows(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
    private static function one(string $sql, array $p = []): mixed { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
    private static function series(int $days, string $sql): array
    {
        $map = array_column(self::rows($sql), 'value', 'd'); $out = [];
        for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $out[] = ['label' => $d, 'value' => (float) ($map[$d] ?? 0)]; }
        return $out;
    }
    private static function today(): array
    {
        return db()->query("SELECT COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales, COALESCE(AVG(b.total),0) AS avg_bill
            FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND DATE(o.closed_at) = CURDATE()")->fetch();
    }
    private static function todaysReservations(): array
    {
        return self::rows("SELECT r.id, r.res_no, r.guest_name, r.party_size, TIME_FORMAT(r.res_time,'%H:%i') AS time, r.status, r.source, t.table_no
            FROM reservations r LEFT JOIN dining_tables t ON t.id = r.table_id WHERE r.res_date = CURDATE() AND r.status IN ('Booked','Seated') ORDER BY r.res_time");
    }

    public static function admin(): array
    {
        $t = self::today();
        return [
            'today' => $t,
            'tables' => db()->query("SELECT SUM(status = 'Occupied') AS occupied, COUNT(*) AS total FROM dining_tables WHERE is_active = 1")->fetch(),
            'active_orders' => (int) self::one("SELECT COUNT(*) FROM orders WHERE status IN ('Open','Billed')"),
            'online_open' => (int) self::one("SELECT COUNT(*) FROM orders WHERE source = 'Online' AND status IN ('Open','Billed')"),
            'unpaid' => (float) self::one("SELECT COALESCE(SUM(total - paid),0) FROM bills WHERE status <> 'Paid'"),
            'sales_chart' => self::series(14, "SELECT DATE(o.closed_at) AS d, SUM(b.total) AS value FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND o.closed_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d"),
            'top_items' => self::rows("SELECT i.name AS label, SUM(i.qty) AS value FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.status = 'Served' AND o.created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY i.menu_item_id, i.name ORDER BY value DESC LIMIT 6"),
            'type_chart' => self::rows("SELECT o.type AS label, SUM(b.total) AS value FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND o.closed_at >= CURDATE() - INTERVAL 30 DAY GROUP BY o.type"),
            'low_stock' => IngredientModel::low(),
            'reservations' => self::todaysReservations(),
            'kitchen' => KitchenModel::stats(),
            'soldout' => self::rows('SELECT name FROM menu_items WHERE is_active = 1 AND is_available = 0 ORDER BY name'),
        ];
    }

    public static function waiter(int $uid): array
    {
        return [
            'mine' => self::rows("SELECT o.id, o.order_no, t.table_no, o.guests, o.status, o.created_at,
                    (SELECT COALESCE(SUM(unit_price * qty),0) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS running,
                    (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.status = 'Ready') AS ready
                FROM orders o LEFT JOIN dining_tables t ON t.id = o.table_id WHERE o.status IN ('Open','Billed') AND o.waiter_id = :u ORDER BY o.created_at", [':u' => $uid]),
            'ready' => self::rows("SELECT i.id, i.name, i.qty, o.id AS order_id, o.order_no, t.table_no, o.type, TIMESTAMPDIFF(MINUTE, i.ready_at, NOW()) AS waiting
                FROM order_items i JOIN orders o ON o.id = i.order_id LEFT JOIN dining_tables t ON t.id = o.table_id WHERE i.status = 'Ready' AND o.status = 'Open' ORDER BY i.ready_at"),
            'today' => db()->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND DATE(o.closed_at) = CURDATE() AND o.waiter_id = :u") ? self::rows("SELECT COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.status = 'Paid' AND DATE(o.closed_at) = CURDATE() AND o.waiter_id = :u", [':u' => $uid])[0] : [],
            'free_tables' => (int) self::one("SELECT COUNT(*) FROM dining_tables WHERE is_active = 1 AND status = 'Available'"),
            'reservations' => self::todaysReservations(),
        ];
    }

    public static function chef(): array
    {
        return [
            'stats' => KitchenModel::stats(),
            'queue' => KitchenModel::board(),
            'low_stock' => IngredientModel::low(),
            'soldout' => self::rows('SELECT id, name FROM menu_items WHERE is_active = 1 AND is_available = 0 ORDER BY name'),
            'today_items' => self::rows("SELECT i.name AS label, SUM(i.qty) AS value FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.status IN ('Preparing','Ready','Served') AND DATE(i.started_at) = CURDATE() GROUP BY i.menu_item_id, i.name ORDER BY value DESC LIMIT 6"),
        ];
    }

    public static function cashier(): array
    {
        $t = self::today();
        return [
            'today' => $t,
            'collected' => (float) self::one('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = CURDATE()'),
            'methods' => self::rows('SELECT method AS label, SUM(amount) AS value FROM payments WHERE DATE(paid_at) = CURDATE() GROUP BY method'),
            'billable' => BillModel::billable(),
            'due' => self::rows("SELECT b.id, b.bill_no, b.total, b.paid, o.order_no, t.table_no, COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer_name FROM bills b JOIN orders o ON o.id = b.order_id
                LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN customers c ON c.id = o.customer_id WHERE b.status <> 'Paid' ORDER BY b.created_at"),
            'recent' => self::rows("SELECT p.amount, p.method, p.paid_at, b.id AS bill_id, b.bill_no FROM payments p JOIN bills b ON b.id = p.bill_id ORDER BY p.id DESC LIMIT 6"),
            'chart' => self::series(14, 'SELECT DATE(paid_at) AS d, SUM(amount) AS value FROM payments WHERE paid_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d'),
        ];
    }

    public static function customer(int $cid): array
    {
        return [
            'profile' => CustomerModel::find($cid),
            'orders' => self::rows("SELECT o.id, o.order_no, o.type, o.status, o.created_at, (SELECT COALESCE(SUM(unit_price * qty),0) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS subtotal,
                (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.status = 'Ready') AS ready FROM orders o WHERE o.customer_id = :c ORDER BY o.id DESC LIMIT 5", [':c' => $cid]),
            'reservations' => self::rows("SELECT r.*, t.table_no FROM reservations r LEFT JOIN dining_tables t ON t.id = r.table_id WHERE r.customer_id = :c AND r.status = 'Booked' AND r.res_date >= CURDATE() ORDER BY r.res_date, r.res_time LIMIT 5", [':c' => $cid]),
            'unpaid' => self::rows("SELECT b.id, b.bill_no, b.total, b.paid FROM bills b JOIN orders o ON o.id = b.order_id WHERE o.customer_id = :c AND b.status <> 'Paid'", [':c' => $cid]),
            'favourites' => CustomerModel::favourites($cid),
        ];
    }
}
