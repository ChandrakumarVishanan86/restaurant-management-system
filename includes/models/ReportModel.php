<?php
declare(strict_types=1);

final class ReportModel
{
    private static function one(string $sql, array $p): mixed { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
    private static function rows(string $sql, array $p): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }

    public static function summary(string $from, string $to): array
    {
        $p = [':f' => $from, ':t' => $to];
        $paidOrders = "o.status = 'Paid' AND DATE(o.closed_at) BETWEEN :f AND :t";
        $head = self::rows("SELECT COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales, COALESCE(SUM(b.subtotal),0) AS gross, COALESCE(SUM(b.discount),0) AS discounts,
            COALESCE(SUM(b.points_used * " . POINT_VALUE . "),0) AS points_value, COALESCE(SUM(b.service_charge),0) AS service, COALESCE(SUM(b.tax),0) AS tax
            FROM bills b JOIN orders o ON o.id = b.order_id WHERE $paidOrders", $p)[0];
        $head['avg_bill'] = $head['orders'] ? round((float) $head['sales'] / (int) $head['orders'], 2) : 0;
        $head['guests'] = (int) self::one("SELECT COALESCE(SUM(o.guests),0) FROM orders o WHERE o.type = 'Dine-in' AND $paidOrders", $p);
        $head['collected'] = (float) self::one('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) BETWEEN :f AND :t', $p);
        $head['outstanding'] = (float) db()->query("SELECT COALESCE(SUM(total - paid),0) FROM bills WHERE status <> 'Paid'")->fetchColumn();
        $head['cancelled'] = (int) self::one("SELECT COUNT(*) FROM orders WHERE status = 'Cancelled' AND DATE(created_at) BETWEEN :f AND :t", $p);
        $itemsJoin = "FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.status = 'Served' AND $paidOrders";
        $head['food_cost'] = (float) self::one("SELECT COALESCE(SUM(-m.qty * g.cost_per_unit),0) FROM stock_movements m JOIN ingredients g ON g.id = m.ingredient_id WHERE m.type = 'Usage' AND DATE(m.created_at) BETWEEN :f AND :t", $p);
        $head['wastage_cost'] = (float) self::one("SELECT COALESCE(SUM(-m.qty * g.cost_per_unit),0) FROM stock_movements m JOIN ingredients g ON g.id = m.ingredient_id WHERE m.type = 'Wastage' AND DATE(m.created_at) BETWEEN :f AND :t", $p);
        $head['stock_value'] = (float) db()->query('SELECT COALESCE(SUM(stock * cost_per_unit),0) FROM ingredients')->fetchColumn();
        return [
            'head' => $head,
            'by_type' => self::rows("SELECT o.type AS label, COALESCE(SUM(b.total),0) AS value FROM bills b JOIN orders o ON o.id = b.order_id WHERE $paidOrders GROUP BY o.type ORDER BY value DESC", $p),
            'by_source' => self::rows("SELECT o.source AS label, COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS value FROM bills b JOIN orders o ON o.id = b.order_id WHERE $paidOrders GROUP BY o.source", $p),
            'methods' => self::rows('SELECT method AS label, COALESCE(SUM(amount),0) AS value FROM payments WHERE DATE(paid_at) BETWEEN :f AND :t GROUP BY method ORDER BY value DESC', $p),
            'daily' => self::rows("SELECT DATE(o.closed_at) AS d, COALESCE(SUM(b.total),0) AS value, COUNT(*) AS orders FROM bills b JOIN orders o ON o.id = b.order_id WHERE $paidOrders GROUP BY DATE(o.closed_at) ORDER BY d", $p),
            'hourly' => self::rows("SELECT HOUR(o.created_at) AS h, COUNT(*) AS orders FROM orders o WHERE $paidOrders GROUP BY HOUR(o.created_at) ORDER BY h", $p),
            'top_items' => self::rows("SELECT i.name, SUM(i.qty) AS qty, SUM(i.qty * i.unit_price) AS revenue $itemsJoin GROUP BY i.menu_item_id, i.name ORDER BY qty DESC LIMIT 10", $p),
            'categories' => self::rows("SELECT c.name AS label, SUM(i.qty * i.unit_price) AS value FROM order_items i JOIN orders o ON o.id = i.order_id JOIN menu_items m ON m.id = i.menu_item_id JOIN categories c ON c.id = m.category_id
                WHERE i.status = 'Served' AND $paidOrders GROUP BY c.id, c.name ORDER BY value DESC", $p),
            'waiters' => self::rows("SELECT u.full_name, COUNT(*) AS orders, COALESCE(SUM(b.total),0) AS sales, COALESCE(AVG(b.total),0) AS avg_bill FROM bills b JOIN orders o ON o.id = b.order_id JOIN users u ON u.id = o.waiter_id
                WHERE $paidOrders GROUP BY u.id, u.full_name ORDER BY sales DESC", $p),
            'customers' => self::rows("SELECT c.full_name, COUNT(*) AS orders, SUM(b.total) AS spent FROM bills b JOIN orders o ON o.id = b.order_id JOIN customers c ON c.id = o.customer_id
                WHERE $paidOrders GROUP BY c.id, c.full_name ORDER BY spent DESC LIMIT 6", $p),
            'reservations' => self::rows("SELECT status AS label, COUNT(*) AS value FROM reservations WHERE res_date BETWEEN :f AND :t GROUP BY status", $p),
        ];
    }

    public static function billRows(string $from, string $to): array
    {
        return self::rows("SELECT b.bill_no, DATE_FORMAT(o.closed_at, '%Y-%m-%d %H:%i') AS paid_on, o.order_no, o.type, COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer,
                b.subtotal, b.discount, b.points_used, b.service_charge, b.tax, b.total, b.paid, b.status
            FROM bills b JOIN orders o ON o.id = b.order_id LEFT JOIN customers c ON c.id = o.customer_id
            WHERE DATE(b.created_at) BETWEEN :f AND :t ORDER BY b.id", [':f' => $from, ':t' => $to]);
    }
}
