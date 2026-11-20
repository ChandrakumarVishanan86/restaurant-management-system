<?php
declare(strict_types=1);

/** Billing: totals (discount, loyalty points, service charge, VAT), payments, loyalty accrual. */
final class BillModel
{
    public const METHODS = ['Cash', 'Card', 'Online'];

    public static function calc(float $sub, float $discount, int $points, bool $dineIn): array
    {
        $discount = round(min(max($discount, 0), $sub), 2);
        $pointValue = round($points * POINT_VALUE, 2);
        $base = max(round($sub - $discount - $pointValue, 2), 0);
        $sc = $dineIn ? round($base * SERVICE_CHARGE_PCT / 100, 2) : 0.0;
        $tax = round(($base + $sc) * TAX_PCT / 100, 2);
        return ['subtotal' => round($sub, 2), 'discount' => $discount, 'points' => $points, 'points_value' => $pointValue, 'service' => $sc, 'tax' => $tax, 'total' => round($base + $sc + $tax, 2)];
    }

    public static function find(int $id): ?array
    {
        $s = db()->prepare("SELECT b.*, (b.total - b.paid) AS balance, o.order_no, o.type, o.table_id, o.customer_id, o.guests, o.created_at AS order_at, o.guest_name, t.table_no,
                COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer_name, c.code AS customer_code, c.loyalty_points, w.full_name AS waiter_name, cb.full_name AS cashier_name
            FROM bills b JOIN orders o ON o.id = b.order_id LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN customers c ON c.id = o.customer_id
            LEFT JOIN users w ON w.id = o.waiter_id LEFT JOIN users cb ON cb.id = b.created_by WHERE b.id = :id");
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function canAccess(array $user, array $b): bool
    {
        if (in_array($user['role'], ['admin', 'cashier'], true)) { return true; }
        return $user['role'] === 'customer' && $b['customer_id'] !== null && (int) $b['customer_id'] === (int) $user['customer_id'];
    }
    public static function items(int $orderId): array
    {
        $s = db()->prepare("SELECT name, unit_price, SUM(qty) AS qty, SUM(unit_price * qty) AS amount FROM order_items WHERE order_id = :o AND status <> 'Cancelled' GROUP BY menu_item_id, name, unit_price ORDER BY MIN(id)");
        $s->execute([':o' => $orderId]);
        return $s->fetchAll();
    }
    public static function payments(int $billId): array
    {
        $s = db()->prepare('SELECT p.*, u.full_name AS received_name FROM payments p LEFT JOIN users u ON u.id = p.received_by WHERE p.bill_id = :b ORDER BY p.id');
        $s->execute([':b' => $billId]);
        return $s->fetchAll();
    }
    public static function list(array $user, string $status = ''): array
    {
        $where = []; $p = [];
        if ($user['role'] === 'customer') { $where[] = 'o.customer_id = :cid'; $p[':cid'] = (int) $user['customer_id']; }
        elseif (!in_array($user['role'], ['admin', 'cashier'], true)) { return []; }
        if (in_array($status, ['Unpaid', 'Partial', 'Paid'], true)) { $where[] = 'b.status = :st'; $p[':st'] = $status; }
        elseif ($status === 'due') { $where[] = "b.status <> 'Paid'"; }
        $s = db()->prepare("SELECT b.id, b.bill_no, b.total, b.paid, (b.total - b.paid) AS balance, b.status, b.created_at, o.order_no, o.type, t.table_no, COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer_name
            FROM bills b JOIN orders o ON o.id = b.order_id LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN customers c ON c.id = o.customer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(b.status,'Unpaid','Partial','Paid'), b.created_at DESC LIMIT 800");
        $s->execute($p);
        return $s->fetchAll();
    }
    /** Open orders that are ready to be billed (nothing still in the kitchen). */
    public static function billable(): array
    {
        return db()->query("SELECT o.id, o.order_no, o.type, t.table_no, COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer_name,
                (SELECT COALESCE(SUM(unit_price * qty), 0) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS subtotal
            FROM orders o LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.status = 'Open' AND EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND i.status IN ('Ready','Served'))
              AND NOT EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND i.status IN ('Draft','New','Preparing'))
            ORDER BY o.created_at")->fetchAll();
    }

    /** Server-side quote used for the billing preview and for generate(). */
    public static function quote(array $order, array $in, array $user): array
    {
        $sub = OrderModel::subtotal((int) $order['id']);
        $type = (string) ($in['discount_type'] ?? 'amount'); $val = max((float) ($in['discount_value'] ?? 0), 0);
        $disc = $type === 'percent' ? round($sub * min($val, 100) / 100, 2) : $val;
        $points = max((int) ($in['points'] ?? 0), 0);
        $err = null;
        if ($disc > 0 && !in_array($user['role'], ['admin', 'cashier'], true)) { $err = 'Only the cashier or manager can give discounts.'; }
        elseif ($disc > $sub) { $err = 'Discount cannot exceed the subtotal.'; }
        elseif ($user['role'] === 'cashier' && $disc > $sub * 0.20 + 0.001) { $err = 'Discounts above 20% need the manager.'; }
        if (!$err && $points > 0) {
            if (!$order['customer_id']) { $err = 'Loyalty points need a registered customer on the order.'; }
            elseif ($points > (int) $order['loyalty_points']) { $err = 'Customer only has ' . (int) $order['loyalty_points'] . ' points.'; }
            elseif ($points * POINT_VALUE > ($sub - $disc) * MAX_REDEEM_PCT / 100 + 0.001) { $err = 'Points can cover at most ' . MAX_REDEEM_PCT . '% of the bill.'; }
        }
        return [self::calc($sub, $disc, $err ? 0 : $points, $order['type'] === 'Dine-in'), $err];
    }

    /** Returns [billId, error]. */
    public static function generate(int $orderId, array $in, array $user): array
    {
        if (!in_array($user['role'], Auth::FLOOR, true)) { return [0, 'Not allowed.']; }
        $pdo = db(); $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE'); $s->execute([':id' => $orderId]); $o = $s->fetch();
            if (!$o) { throw new RuntimeException('Order not found.'); }
            if ($o['status'] !== 'Open') { throw new RuntimeException($o['status'] === 'Billed' ? 'This order already has a bill.' : 'This order is ' . strtolower($o['status']) . '.'); }
            $pend = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :o AND status IN ('Draft','New','Preparing')"); $pend->execute([':o' => $orderId]);
            if ((int) $pend->fetchColumn() > 0) { throw new RuntimeException('Some items are still unsent or in the kitchen.'); }
            $o['loyalty_points'] = 0;
            if ($o['customer_id']) {
                $c = $pdo->prepare('SELECT id, loyalty_points FROM customers WHERE id = :id FOR UPDATE'); $c->execute([':id' => $o['customer_id']]); $o['loyalty_points'] = (int) ($c->fetch()['loyalty_points'] ?? 0);
            }
            [$q, $err] = self::quote($o, $in, $user);
            if ($err) { throw new RuntimeException($err); }
            if ($q['subtotal'] <= 0) { throw new RuntimeException('There are no items to bill.'); }
            if ($q['total'] <= 0) { throw new RuntimeException('The total is zero. Reduce the discount or points.'); }
            $pdo->prepare("UPDATE order_items SET status = 'Served', served_at = NOW() WHERE order_id = :o AND status = 'Ready'")->execute([':o' => $orderId]);
            $pdo->prepare('INSERT INTO bills (bill_no, order_id, subtotal, discount, points_used, service_charge, tax, total, created_by) VALUES (:n,:o,:s,:d,:p,:sc,:t,:tt,:u)')
                ->execute([':n' => 'TMP' . bin2hex(random_bytes(5)), ':o' => $orderId, ':s' => $q['subtotal'], ':d' => $q['discount'], ':p' => $q['points'], ':sc' => $q['service'], ':t' => $q['tax'], ':tt' => $q['total'], ':u' => (int) $user['id']]);
            $bid = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE bills SET bill_no = :n WHERE id = :id')->execute([':n' => sprintf('BIL-%05d', $bid), ':id' => $bid]);
            if ($q['points'] > 0) { $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points - :p WHERE id = :c')->execute([':p' => $q['points'], ':c' => $o['customer_id']]); }
            $pdo->prepare("UPDATE orders SET status = 'Billed' WHERE id = :o")->execute([':o' => $orderId]);
            $pdo->commit();
        } catch (RuntimeException $e) { $pdo->rollBack(); return [0, $e->getMessage()]; }
        Audit::log('create', 'bill', $bid, sprintf('BIL-%05d total %s', $bid, number_format($q['total'], 2)));
        return [$bid, null];
    }

    /** Cancel an unpaid bill and reopen the order (points are refunded). */
    public static function reopen(int $billId, array $user): ?string
    {
        if (!in_array($user['role'], ['admin', 'cashier'], true)) { return 'Not allowed.'; }
        $pdo = db(); $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT b.*, o.customer_id FROM bills b JOIN orders o ON o.id = b.order_id WHERE b.id = :id FOR UPDATE'); $s->execute([':id' => $billId]); $b = $s->fetch();
        if (!$b) { $pdo->rollBack(); return 'Bill not found.'; }
        if ((float) $b['paid'] > 0) { $pdo->rollBack(); return 'This bill already has payments and cannot be cancelled.'; }
        if ((int) $b['points_used'] > 0 && $b['customer_id']) { $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + :p WHERE id = :c')->execute([':p' => $b['points_used'], ':c' => $b['customer_id']]); }
        $pdo->prepare('DELETE FROM bills WHERE id = :id')->execute([':id' => $billId]);
        $pdo->prepare("UPDATE orders SET status = 'Open' WHERE id = :o")->execute([':o' => $b['order_id']]);
        $pdo->commit();
        Audit::log('cancel', 'bill', $billId, $b['bill_no'] . ' cancelled, order reopened');
        return null;
    }

    public static function addPayment(int $billId, float $amount, string $method, ?string $ref, array $user): ?string
    {
        if (!in_array($user['role'], ['admin', 'cashier'], true)) { return 'Only the cashier or manager can take payments.'; }
        if (!in_array($method, self::METHODS, true)) { return 'Choose Cash, Card or Online.'; }
        $amount = round($amount, 2);
        if ($amount <= 0) { return 'Enter an amount above zero.'; }
        if ($ref !== null && mb_strlen($ref) > 60) { return 'Reference is too long.'; }
        $pdo = db(); $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT b.*, o.table_id, o.customer_id, o.reservation_id FROM bills b JOIN orders o ON o.id = b.order_id WHERE b.id = :id FOR UPDATE'); $s->execute([':id' => $billId]); $b = $s->fetch();
        if (!$b) { $pdo->rollBack(); return 'Bill not found.'; }
        $bal = round((float) $b['total'] - (float) $b['paid'], 2);
        if ($b['status'] === 'Paid' || $bal <= 0) { $pdo->rollBack(); return 'This bill is already settled.'; }
        if ($amount > $bal + 0.001) { $pdo->rollBack(); return 'Amount exceeds the balance of ' . money($bal) . '.'; }
        $pdo->prepare('INSERT INTO payments (bill_id, amount, method, reference, received_by) VALUES (:b,:a,:m,:r,:u)')->execute([':b' => $billId, ':a' => $amount, ':m' => $method, ':r' => $ref ?: null, ':u' => (int) $user['id']]);
        $paid = round((float) $b['paid'] + $amount, 2); $done = abs($paid - (float) $b['total']) < 0.005;
        $pdo->prepare('UPDATE bills SET paid = :p, status = :s WHERE id = :id')->execute([':p' => $done ? $b['total'] : $paid, ':s' => $done ? 'Paid' : 'Partial', ':id' => $billId]);
        if ($done) {
            $pdo->prepare("UPDATE orders SET status = 'Paid', closed_at = NOW() WHERE id = :o")->execute([':o' => $b['order_id']]);
            if ($b['customer_id']) {
                $earn = (int) floor((float) $b['total'] / POINTS_PER_RS);
                $pdo->prepare('UPDATE customers SET total_spent = total_spent + :t, visits = visits + 1, loyalty_points = loyalty_points + :p WHERE id = :c')->execute([':t' => $b['total'], ':p' => $earn, ':c' => $b['customer_id']]);
            }
            if ($b['reservation_id']) { $pdo->prepare("UPDATE reservations SET status = 'Completed' WHERE id = :r AND status = 'Seated'")->execute([':r' => $b['reservation_id']]); }
        }
        $pdo->commit();
        if ($done && $b['table_id']) { OrderModel::afterPaid(['table_id' => $b['table_id']]); }
        Audit::log('payment', 'bill', $billId, $b['bill_no'] . ' ' . $method . ' ' . number_format($amount, 2) . ($done ? ' (settled)' : ''));
        return null;
    }
}
