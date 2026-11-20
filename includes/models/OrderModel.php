<?php
declare(strict_types=1);

/** Orders, order lines and the KOT flow (Draft -> New -> Preparing -> Ready -> Served). */
final class OrderModel
{
    public const TYPES = ['Dine-in', 'Takeaway', 'Delivery'];

    public static function find(int $id): ?array
    {
        $s = db()->prepare("SELECT o.*, t.table_no, t.area, t.seats, w.full_name AS waiter_name, c.full_name AS customer_name, c.code AS customer_code,
                c.phone AS customer_phone, c.loyalty_points, b.id AS bill_id, b.bill_no, b.total AS bill_total, b.paid AS bill_paid, b.status AS bill_status
            FROM orders o LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN users w ON w.id = o.waiter_id
            LEFT JOIN customers c ON c.id = o.customer_id LEFT JOIN bills b ON b.order_id = o.id WHERE o.id = :id");
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function canAccess(array $user, array $o): bool
    {
        if (in_array($user['role'], Auth::FLOOR, true)) { return true; }
        return $user['role'] === 'customer' && $o['customer_id'] !== null && (int) $o['customer_id'] === (int) $user['customer_id'];
    }

    public static function list(array $user, string $status = '', int $limit = 600): array
    {
        $where = []; $p = [];
        if ($user['role'] === 'customer') { $where[] = 'o.customer_id = :cid'; $p[':cid'] = (int) $user['customer_id']; }
        elseif (!in_array($user['role'], Auth::FLOOR, true)) { return []; }
        if ($status === 'active') { $where[] = "o.status IN ('Open','Billed')"; }
        elseif ($status !== '' && in_array($status, ['Open', 'Billed', 'Paid', 'Cancelled'], true)) { $where[] = 'o.status = :st'; $p[':st'] = $status; }
        $sql = "SELECT o.id, o.order_no, o.type, o.source, o.status, o.guests, o.created_at, t.table_no, w.full_name AS waiter_name,
                COALESCE(c.full_name, o.guest_name, 'Walk-in') AS customer_name,
                (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS item_count,
                (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.status = 'Ready') AS ready_count,
                (SELECT COALESCE(SUM(i.unit_price * i.qty), 0) FROM order_items i WHERE i.order_id = o.id AND i.status <> 'Cancelled') AS subtotal,
                b.total AS bill_total, b.status AS bill_status
            FROM orders o LEFT JOIN dining_tables t ON t.id = o.table_id LEFT JOIN users w ON w.id = o.waiter_id LEFT JOIN customers c ON c.id = o.customer_id LEFT JOIN bills b ON b.order_id = o.id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(o.status,'Open','Billed','Paid','Cancelled'), o.created_at DESC LIMIT $limit";
        $s = db()->prepare($sql); $s->execute($p);
        return $s->fetchAll();
    }

    public static function items(int $orderId): array
    {
        $s = db()->prepare('SELECT i.*, m.icon FROM order_items i JOIN menu_items m ON m.id = i.menu_item_id WHERE i.order_id = :id ORDER BY i.id');
        $s->execute([':id' => $orderId]);
        return $s->fetchAll();
    }
    public static function subtotal(int $orderId): float
    {
        $s = db()->prepare("SELECT COALESCE(SUM(unit_price * qty), 0) FROM order_items WHERE order_id = :id AND status <> 'Cancelled'");
        $s->execute([':id' => $orderId]);
        return (float) $s->fetchColumn();
    }
    private static function lock(int $id): ?array
    {
        $s = db()->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    private static function setNo(int $id): string
    {
        $no = sprintf('ORD-%05d', $id);
        db()->prepare('UPDATE orders SET order_no = :n WHERE id = :id')->execute([':n' => $no, ':id' => $id]);
        return $no;
    }
    private static function validCustomer(?int $cid): bool
    {
        if (!$cid) { return true; }
        $s = db()->prepare('SELECT 1 FROM customers WHERE id = :id AND is_active = 1'); $s->execute([':id' => $cid]);
        return (bool) $s->fetchColumn();
    }
    private static function freeTableIfIdle(int $tableId): void
    {
        $s = db()->prepare("SELECT COUNT(*) FROM orders WHERE table_id = :t AND status IN ('Open','Billed')"); $s->execute([':t' => $tableId]);
        if ((int) $s->fetchColumn() === 0) { db()->prepare("UPDATE dining_tables SET status = 'Cleaning' WHERE id = :t")->execute([':t' => $tableId]); }
    }

    /** Create an order (staff). Returns [id, error]. */
    public static function open(array $user, array $in): array
    {
        $type = (string) ($in['type'] ?? 'Dine-in');
        if (!in_array($type, self::TYPES, true)) { return [0, 'Choose an order type.']; }
        $guests = (int) ($in['guests'] ?? 1);
        if ($guests < 1 || $guests > 30) { return [0, 'Guests must be between 1 and 30.']; }
        $cid = (int) ($in['customer_id'] ?? 0) ?: null;
        if (!self::validCustomer($cid)) { return [0, 'Unknown customer.']; }
        $name = trim((string) ($in['guest_name'] ?? '')); $phone = trim((string) ($in['guest_phone'] ?? ''));
        $addr = trim((string) ($in['delivery_address'] ?? '')); $note = trim((string) ($in['note'] ?? ''));
        if (mb_strlen($name) > 120 || mb_strlen($addr) > 255 || mb_strlen($note) > 255) { return [0, 'A text field is too long.']; }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) { return [0, 'Enter a valid phone number.']; }
        $waiter = $user['role'] === 'waiter' ? (int) $user['id'] : ((int) ($in['waiter_id'] ?? 0) ?: null);
        if ($waiter && $user['role'] !== 'waiter') {
            $w = db()->prepare("SELECT 1 FROM users WHERE id = :id AND role = 'waiter' AND is_active = 1"); $w->execute([':id' => $waiter]);
            if (!$w->fetchColumn()) { return [0, 'Choose a valid waiter.']; }
        }
        $tableId = null; $resId = (int) ($in['reservation_id'] ?? 0) ?: null;
        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($type === 'Dine-in') {
                $tableId = (int) ($in['table_id'] ?? 0);
                $s = $pdo->prepare('SELECT * FROM dining_tables WHERE id = :id AND is_active = 1 FOR UPDATE'); $s->execute([':id' => $tableId]); $t = $s->fetch();
                if (!$t) { throw new RuntimeException('Choose a table.'); }
                if ($t['status'] !== 'Available') { throw new RuntimeException('Table ' . $t['table_no'] . ' is not available (' . $t['status'] . ').'); }
                if ($guests > (int) $t['seats']) { throw new RuntimeException('Table ' . $t['table_no'] . ' seats only ' . $t['seats'] . '.'); }
            } else {
                if (!$cid && $name === '') { throw new RuntimeException('Enter the customer name or pick a customer.'); }
                if ($type === 'Delivery' && mb_strlen($addr) < 5) { throw new RuntimeException('Delivery address is required.'); }
                if ($type === 'Delivery' && !$cid && $phone === '') { throw new RuntimeException('Delivery needs a contact phone.'); }
                $guests = 1;
            }
            $pdo->prepare("INSERT INTO orders (order_no, type, source, table_id, customer_id, reservation_id, waiter_id, guest_name, guest_phone, guests, delivery_address, note)
                VALUES (:no,:ty,'Staff',:t,:c,:r,:w,:gn,:gp,:g,:da,:nt)")->execute([
                ':no' => 'TMP' . bin2hex(random_bytes(5)), ':ty' => $type, ':t' => $tableId, ':c' => $cid, ':r' => $resId, ':w' => $waiter,
                ':gn' => $name ?: null, ':gp' => $phone ?: null, ':g' => $guests, ':da' => $type === 'Delivery' ? $addr : null, ':nt' => $note ?: null]);
            $id = (int) $pdo->lastInsertId(); $no = self::setNo($id);
            if ($tableId) { $pdo->prepare("UPDATE dining_tables SET status = 'Occupied' WHERE id = :id")->execute([':id' => $tableId]); }
            $pdo->commit();
        } catch (RuntimeException $e) { $pdo->rollBack(); return [0, $e->getMessage()]; }
        Audit::log('create', 'order', $id, "$no ($type)");
        return [$id, null];
    }

    public static function addItem(int $orderId, int $menuId, int $qty, string $note, array $user): ?string
    {
        if ($qty < 1 || $qty > 50) { return 'Quantity must be between 1 and 50.'; }
        $note = trim($note); if (mb_strlen($note) > 120) { return 'Note is too long.'; }
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o) { $pdo->rollBack(); return 'Order not found.'; }
        if ($o['status'] !== 'Open') { $pdo->rollBack(); return 'This order is ' . strtolower($o['status']) . ' and cannot be changed.'; }
        $m = $pdo->prepare('SELECT m.*, c.station FROM menu_items m JOIN categories c ON c.id = m.category_id WHERE m.id = :id'); $m->execute([':id' => $menuId]); $m = $m->fetch();
        if (!$m || !$m['is_active']) { $pdo->rollBack(); return 'That item is not on the menu.'; }
        if (!$m['is_available']) { $pdo->rollBack(); return $m['name'] . ' is sold out today.'; }
        $x = $pdo->prepare("SELECT id, qty FROM order_items WHERE order_id = :o AND menu_item_id = :m AND status = 'Draft' AND COALESCE(note,'') = :n LIMIT 1");
        $x->execute([':o' => $orderId, ':m' => $menuId, ':n' => $note]); $ex = $x->fetch();
        if ($ex) {
            $nq = (int) $ex['qty'] + $qty; if ($nq > 50) { $pdo->rollBack(); return 'Maximum 50 of one item per line.'; }
            $pdo->prepare('UPDATE order_items SET qty = :q WHERE id = :id')->execute([':q' => $nq, ':id' => $ex['id']]);
        } else {
            $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, name, unit_price, qty, note, status, station) VALUES (:o,:m,:n,:p,:q,:nt,'Draft',:s)")
                ->execute([':o' => $orderId, ':m' => $menuId, ':n' => $m['name'], ':p' => $m['price'], ':q' => $qty, ':nt' => $note ?: null, ':s' => $m['station']]);
        }
        $pdo->commit();
        return null;
    }

    public static function setQty(int $itemId, int $qty): ?string
    {
        if ($qty < 1 || $qty > 50) { return 'Quantity must be between 1 and 50.'; }
        $pdo = db(); $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT * FROM order_items WHERE id = :id FOR UPDATE'); $s->execute([':id' => $itemId]); $it = $s->fetch();
        if (!$it) { $pdo->rollBack(); return 'Item not found.'; }
        $o = self::lock((int) $it['order_id']);
        if ($o['status'] !== 'Open') { $pdo->rollBack(); return 'This order can no longer be changed.'; }
        if ($it['status'] !== 'Draft') { $pdo->rollBack(); return 'Only items not yet sent to the kitchen can be edited.'; }
        $pdo->prepare('UPDATE order_items SET qty = :q WHERE id = :id')->execute([':q' => $qty, ':id' => $itemId]);
        $pdo->commit();
        return null;
    }

    public static function removeItem(int $itemId, array $user): ?string
    {
        $pdo = db(); $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT * FROM order_items WHERE id = :id FOR UPDATE'); $s->execute([':id' => $itemId]); $it = $s->fetch();
        if (!$it) { $pdo->rollBack(); return 'Item not found.'; }
        $o = self::lock((int) $it['order_id']);
        if ($o['status'] !== 'Open') { $pdo->rollBack(); return 'This order can no longer be changed.'; }
        switch ($it['status']) {
            case 'Draft': $pdo->prepare('DELETE FROM order_items WHERE id = :id')->execute([':id' => $itemId]); break;
            case 'New': $pdo->prepare("UPDATE order_items SET status = 'Cancelled' WHERE id = :id")->execute([':id' => $itemId]); break;
            case 'Preparing': case 'Ready':
                if ($user['role'] !== 'admin') { $pdo->rollBack(); return 'The kitchen already started this dish. Ask the manager to cancel it.'; }
                $pdo->prepare("UPDATE order_items SET status = 'Cancelled' WHERE id = :id")->execute([':id' => $itemId]); break;
            default: $pdo->rollBack(); return 'Served or cancelled items cannot be removed.';
        }
        $pdo->commit();
        if ($it['status'] !== 'Draft') { Audit::log('cancel_item', 'order', (int) $o['id'], $o['order_no'] . ': ' . $it['name'] . ' x' . $it['qty'] . ' (' . $it['status'] . ')'); }
        return null;
    }

    /** Send all drafts to the kitchen as one KOT. Returns [kot_no, error]. */
    public static function send(int $orderId): array
    {
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o) { $pdo->rollBack(); return [0, 'Order not found.']; }
        if ($o['status'] !== 'Open') { $pdo->rollBack(); return [0, 'This order is ' . strtolower($o['status']) . '.']; }
        $c = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :o AND status = 'Draft'"); $c->execute([':o' => $orderId]);
        if ((int) $c->fetchColumn() === 0) { $pdo->rollBack(); return [0, 'Add items before sending to the kitchen.']; }
        $un = $pdo->prepare("SELECT m.name FROM order_items i JOIN menu_items m ON m.id = i.menu_item_id WHERE i.order_id = :o AND i.status = 'Draft' AND m.is_available = 0 LIMIT 1");
        $un->execute([':o' => $orderId]);
        if ($n = $un->fetchColumn()) { $pdo->rollBack(); return [0, $n . ' just sold out. Remove it and try again.']; }
        $kot = (int) $o['kot_count'] + 1;
        $pdo->prepare("UPDATE order_items SET status = 'New', kot_no = :k, sent_at = NOW() WHERE order_id = :o AND status = 'Draft'")->execute([':k' => $kot, ':o' => $orderId]);
        $pdo->prepare('UPDATE orders SET kot_count = :k WHERE id = :o')->execute([':k' => $kot, ':o' => $orderId]);
        $pdo->commit();
        Audit::log('kot', 'order', $orderId, $o['order_no'] . ' KOT #' . $kot);
        return [$kot, null];
    }

    /** Mark Ready items as Served (all, or the given ids). Returns [count, error]. */
    public static function serve(int $orderId, ?array $ids = null): array
    {
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o || $o['status'] !== 'Open') { $pdo->rollBack(); return [0, 'Order is not open.']; }
        $sql = "UPDATE order_items SET status = 'Served', served_at = NOW() WHERE order_id = :o AND status = 'Ready'";
        $p = [':o' => $orderId];
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) { $pdo->rollBack(); return [0, 'Choose items to serve.']; }
            $sql .= ' AND id IN (' . implode(',', $ids) . ')';
        }
        $st = $pdo->prepare($sql); $st->execute($p); $n = $st->rowCount();
        $pdo->commit();
        return $n ? [$n, null] : [0, 'No ready items to serve.'];
    }

    public static function cancel(int $orderId, array $user): ?string
    {
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o) { $pdo->rollBack(); return 'Order not found.'; }
        if ($o['status'] !== 'Open') { $pdo->rollBack(); return $o['status'] === 'Billed' ? 'A bill exists. Cancel the bill first.' : 'This order is already ' . strtolower($o['status']) . '.'; }
        $c = $pdo->prepare("SELECT SUM(status IN ('Preparing','Ready','Served')) FROM order_items WHERE order_id = :o"); $c->execute([':o' => $orderId]);
        if ((int) $c->fetchColumn() > 0 && $user['role'] !== 'admin') { $pdo->rollBack(); return 'Food has already been prepared. Only the manager can cancel this order.'; }
        $pdo->prepare("UPDATE order_items SET status = 'Cancelled' WHERE order_id = :o AND status <> 'Cancelled'")->execute([':o' => $orderId]);
        $pdo->prepare("UPDATE orders SET status = 'Cancelled', closed_at = NOW() WHERE id = :o")->execute([':o' => $orderId]);
        if ($o['table_id']) { $pdo->prepare("UPDATE dining_tables SET status = 'Available' WHERE id = :t AND NOT EXISTS (SELECT 1 FROM orders x WHERE x.table_id = :t2 AND x.status IN ('Open','Billed'))")->execute([':t' => $o['table_id'], ':t2' => $o['table_id']]); }
        $pdo->commit();
        Audit::log('cancel', 'order', $orderId, $o['order_no'] . ' cancelled');
        return null;
    }

    public static function move(int $orderId, int $tableId): ?string
    {
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o || $o['status'] !== 'Open' || $o['type'] !== 'Dine-in') { $pdo->rollBack(); return 'Only open dine-in orders can move tables.'; }
        if ((int) $o['table_id'] === $tableId) { $pdo->rollBack(); return 'Already at that table.'; }
        $s = $pdo->prepare('SELECT * FROM dining_tables WHERE id = :id AND is_active = 1 FOR UPDATE'); $s->execute([':id' => $tableId]); $t = $s->fetch();
        if (!$t || $t['status'] !== 'Available') { $pdo->rollBack(); return 'That table is not available.'; }
        if ((int) $o['guests'] > (int) $t['seats']) { $pdo->rollBack(); return 'Table ' . $t['table_no'] . ' seats only ' . $t['seats'] . '.'; }
        $pdo->prepare("UPDATE dining_tables SET status = 'Available' WHERE id = :id")->execute([':id' => $o['table_id']]);
        $pdo->prepare("UPDATE dining_tables SET status = 'Occupied' WHERE id = :id")->execute([':id' => $tableId]);
        $pdo->prepare('UPDATE orders SET table_id = :t WHERE id = :o')->execute([':t' => $tableId, ':o' => $orderId]);
        $pdo->commit();
        Audit::log('move', 'order', $orderId, $o['order_no'] . ' moved to ' . $t['table_no']);
        return null;
    }

    /** Customer self-service order (goes straight to the kitchen as KOT 1). Returns [id, error]. */
    public static function placeOnline(array $user, array $in): array
    {
        $cid = (int) $user['customer_id'];
        $cust = db()->prepare('SELECT * FROM customers WHERE id = :id AND is_active = 1'); $cust->execute([':id' => $cid]); $cust = $cust->fetch();
        if (!$cust) { return [0, 'Your customer profile is not active.']; }
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, ['Takeaway', 'Delivery'], true)) { return [0, 'Choose takeaway or delivery.']; }
        $addr = trim((string) ($in['delivery_address'] ?? $cust['address'] ?? '')); $note = trim((string) ($in['note'] ?? ''));
        if ($type === 'Delivery' && mb_strlen($addr) < 5) { return [0, 'Enter a delivery address.']; }
        if (mb_strlen($addr) > 255 || mb_strlen($note) > 255) { return [0, 'A text field is too long.']; }
        $lines = [];
        foreach ((array) ($in['lines'] ?? []) as $mid => $qty) {
            $mid = (int) $mid; $qty = (int) $qty;
            if ($mid <= 0 || $qty < 1 || $qty > 20) { return [0, 'Each item quantity must be between 1 and 20.']; }
            $lines[$mid] = $qty;
        }
        if (!$lines) { return [0, 'Your cart is empty.']; }
        $pdo = db(); $pdo->beginTransaction();
        try {
            $open = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = :c AND source = 'Online' AND status IN ('Open','Billed')"); $open->execute([':c' => $cid]);
            if ((int) $open->fetchColumn() >= 3) { throw new RuntimeException('You already have 3 online orders in progress.'); }
            $ph = implode(',', array_fill(0, count($lines), '?'));
            $m = $pdo->prepare("SELECT m.*, c.station FROM menu_items m JOIN categories c ON c.id = m.category_id WHERE m.id IN ($ph)"); $m->execute(array_keys($lines));
            $menu = array_column($m->fetchAll(), null, 'id');
            foreach ($lines as $mid => $qty) {
                if (!isset($menu[$mid]) || !$menu[$mid]['is_active']) { throw new RuntimeException('An item in your cart is no longer on the menu.'); }
                if (!$menu[$mid]['is_available']) { throw new RuntimeException($menu[$mid]['name'] . ' is sold out right now.'); }
            }
            $pdo->prepare("INSERT INTO orders (order_no, type, source, customer_id, guest_phone, guests, delivery_address, note, kot_count) VALUES (:no,:ty,'Online',:c,:ph,1,:da,:nt,1)")
                ->execute([':no' => 'TMP' . bin2hex(random_bytes(5)), ':ty' => $type, ':c' => $cid, ':ph' => $cust['phone'], ':da' => $type === 'Delivery' ? $addr : null, ':nt' => $note ?: null]);
            $id = (int) $pdo->lastInsertId(); $no = self::setNo($id);
            $ins = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, name, unit_price, qty, status, station, kot_no, sent_at) VALUES (:o,:m,:n,:p,:q,'New',:s,1,NOW())");
            foreach ($lines as $mid => $qty) { $ins->execute([':o' => $id, ':m' => $mid, ':n' => $menu[$mid]['name'], ':p' => $menu[$mid]['price'], ':q' => $qty, ':s' => $menu[$mid]['station']]); }
            $pdo->commit();
        } catch (RuntimeException $e) { $pdo->rollBack(); return [0, $e->getMessage()]; }
        Audit::log('create', 'order', $id, "$no online ($type)");
        return [$id, null];
    }

    public static function customerCancel(int $orderId, array $user): ?string
    {
        $pdo = db(); $pdo->beginTransaction();
        $o = self::lock($orderId);
        if (!$o || (int) $o['customer_id'] !== (int) $user['customer_id']) { $pdo->rollBack(); return 'Order not found.'; }
        if ($o['source'] !== 'Online' || $o['status'] !== 'Open') { $pdo->rollBack(); return 'This order can no longer be cancelled.'; }
        $c = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :o AND status NOT IN ('New','Cancelled')"); $c->execute([':o' => $orderId]);
        if ((int) $c->fetchColumn() > 0) { $pdo->rollBack(); return 'The kitchen has already started your order.'; }
        $pdo->prepare("UPDATE order_items SET status = 'Cancelled' WHERE order_id = :o")->execute([':o' => $orderId]);
        $pdo->prepare("UPDATE orders SET status = 'Cancelled', closed_at = NOW() WHERE id = :o")->execute([':o' => $orderId]);
        $pdo->commit();
        Audit::log('cancel', 'order', $orderId, $o['order_no'] . ' cancelled by customer');
        return null;
    }

    public static function afterPaid(array $order): void { if ($order['table_id']) { self::freeTableIfIdle((int) $order['table_id']); } }
}
