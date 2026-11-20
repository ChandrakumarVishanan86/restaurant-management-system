<?php
declare(strict_types=1);

/** Table reservations: slot availability, race-safe booking, seating (opens a dine-in order). */
final class ReservationModel
{
    public static function find(int $id): ?array
    {
        $s = db()->prepare('SELECT r.*, t.table_no, t.area, t.seats FROM reservations r LEFT JOIN dining_tables t ON t.id = r.table_id WHERE r.id = :id');
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function canAccess(array $user, array $r): bool
    {
        if (in_array($user['role'], Auth::FLOOR, true)) { return true; }
        return $user['role'] === 'customer' && $r['customer_id'] !== null && (int) $r['customer_id'] === (int) $user['customer_id'];
    }
    public static function list(array $user, ?string $from = null, ?string $to = null, string $status = ''): array
    {
        $where = []; $p = [];
        if ($user['role'] === 'customer') { $where[] = 'r.customer_id = :cid'; $p[':cid'] = (int) $user['customer_id']; }
        elseif (!in_array($user['role'], Auth::FLOOR, true)) { return []; }
        if ($from) { $where[] = 'r.res_date >= :f'; $p[':f'] = $from; }
        if ($to) { $where[] = 'r.res_date <= :t'; $p[':t'] = $to; }
        if ($status !== '') { $where[] = 'r.status = :s'; $p[':s'] = $status; }
        $s = db()->prepare("SELECT r.*, t.table_no, t.area, t.seats FROM reservations r LEFT JOIN dining_tables t ON t.id = r.table_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.res_date DESC, r.res_time DESC LIMIT 500');
        $s->execute($p);
        return $s->fetchAll();
    }

    public static function timeSlots(): array
    {
        $out = []; [$oh, $om] = array_map('intval', explode(':', OPEN_TIME)); [$ch, $cm] = array_map('intval', explode(':', CLOSE_TIME));
        for ($m = $oh * 60 + $om; $m <= $ch * 60 + $cm - 60; $m += 30) { $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60); }
        return $out;
    }

    private static function busyTableIds(string $date, string $time, int $excludeRes = 0): array
    {
        $s = db()->prepare("SELECT DISTINCT table_id FROM reservations WHERE res_date = :d AND status IN ('Booked','Seated') AND table_id IS NOT NULL AND id <> :ex
            AND ABS(TIME_TO_SEC(res_time) - TIME_TO_SEC(:t)) < :stay");
        $s->execute([':d' => $date, ':ex' => $excludeRes, ':t' => $time . ':00', ':stay' => RES_STAY_MINUTES * 60]);
        $busy = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        if ($date === date('Y-m-d')) {
            $ahead = (strtotime($date . ' ' . $time) - time()) / 60;
            if ($ahead < RES_STAY_MINUTES) {
                $o = db()->query("SELECT id FROM dining_tables WHERE status = 'Occupied'")->fetchAll(PDO::FETCH_COLUMN);
                $busy = array_merge($busy, array_map('intval', $o));
            }
        }
        return array_values(array_unique($busy));
    }
    public static function freeTables(string $date, string $time, int $party, int $excludeRes = 0): array
    {
        $busy = self::busyTableIds($date, $time, $excludeRes);
        $rows = db()->prepare('SELECT id, table_no, area, seats FROM dining_tables WHERE is_active = 1 AND seats >= :p ORDER BY seats, table_no');
        $rows->execute([':p' => $party]);
        return array_values(array_filter($rows->fetchAll(), static fn($t) => !in_array((int) $t['id'], $busy, true)));
    }
    public static function slots(string $date, int $party): array
    {
        $out = []; $today = $date === date('Y-m-d');
        foreach (self::timeSlots() as $t) {
            if ($today && strtotime($date . ' ' . $t) < time() + 15 * 60) { continue; }
            $out[] = ['time' => $t, 'free' => count(self::freeTables($date, $t, $party))];
        }
        return $out;
    }

    /** Returns [id, error]. */
    public static function book(array $user, array $in): array
    {
        $isCust = $user['role'] === 'customer';
        if (!$isCust && !in_array($user['role'], Auth::FLOOR, true)) { return [0, 'Not allowed.']; }
        $cid = $isCust ? (int) $user['customer_id'] : ((int) ($in['customer_id'] ?? 0) ?: null);
        $name = trim((string) ($in['guest_name'] ?? '')); $phone = trim((string) ($in['phone'] ?? ''));
        if ($cid) {
            $c = db()->prepare('SELECT full_name, phone FROM customers WHERE id = :id AND is_active = 1'); $c->execute([':id' => $cid]); $c = $c->fetch();
            if (!$c) { return [0, 'Unknown customer.']; }
            if ($isCust || $name === '') { $name = $c['full_name']; }
            if ($isCust || $phone === '') { $phone = $c['phone']; }
        }
        if ($name === '' || mb_strlen($name) > 120) { return [0, 'Enter the guest name.']; }
        if (!preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) { return [0, 'Enter a valid phone number.']; }
        $party = (int) ($in['party_size'] ?? 0);
        if ($party < 1 || $party > 30) { return [0, 'Party size must be between 1 and 30.']; }
        $date = (string) ($in['res_date'] ?? ''); $time = substr((string) ($in['res_time'] ?? ''), 0, 5);
        if (!valid_date($date)) { return [0, 'Choose a valid date.']; }
        if ($date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+' . RES_DAYS_AHEAD . ' days'))) { return [0, 'Reservations open for the next ' . RES_DAYS_AHEAD . ' days.']; }
        if (!in_array($time, self::timeSlots(), true)) { return [0, 'Choose a time between ' . OPEN_TIME . ' and ' . self::timeSlots()[count(self::timeSlots()) - 1] . '.']; }
        if (strtotime("$date $time") < time() + 10 * 60) { return [0, 'That time has already passed.']; }
        $notes = trim((string) ($in['notes'] ?? '')); if (mb_strlen($notes) > 255) { return [0, 'Notes are too long.']; }
        $wantTable = (int) ($in['table_id'] ?? 0);

        $pdo = db(); $pdo->beginTransaction();
        try {
            $pdo->query('SELECT id FROM dining_tables WHERE is_active = 1 FOR UPDATE')->fetchAll();    // serialise concurrent bookings
            $dup = $pdo->prepare("SELECT 1 FROM reservations WHERE phone = :p AND res_date = :d AND status = 'Booked' AND ABS(TIME_TO_SEC(res_time) - TIME_TO_SEC(:t)) < :stay LIMIT 1");
            $dup->execute([':p' => $phone, ':d' => $date, ':t' => $time . ':00', ':stay' => RES_STAY_MINUTES * 60]);
            if ($dup->fetchColumn()) { throw new RuntimeException('This number already has a booking around that time.'); }
            if ($isCust) {
                $n = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = :c AND status = 'Booked' AND res_date >= CURDATE()"); $n->execute([':c' => $cid]);
                if ((int) $n->fetchColumn() >= 3) { throw new RuntimeException('You can hold up to 3 upcoming reservations. Cancel one to book another.'); }
            }
            $free = self::freeTables($date, $time, $party);
            if (!$free) { throw new RuntimeException('No table for ' . $party . ' is free at ' . $time . '. Try another time.'); }
            $table = $free[0]['id'];
            if ($wantTable && !$isCust) {
                $ok = array_filter($free, static fn($t) => (int) $t['id'] === $wantTable);
                if (!$ok) { throw new RuntimeException('That table is not free at that time or is too small.'); }
                $table = $wantTable;
            }
            $pdo->prepare("INSERT INTO reservations (res_no, customer_id, guest_name, phone, party_size, res_date, res_time, table_id, source, notes, created_by)
                VALUES (:no,:c,:n,:p,:ps,:d,:t,:tb,:src,:nt,:u)")->execute([':no' => 'TMP' . bin2hex(random_bytes(4)), ':c' => $cid, ':n' => $name, ':p' => $phone, ':ps' => $party, ':d' => $date, ':t' => $time . ':00',
                ':tb' => $table, ':src' => $isCust ? 'Online' : 'Staff', ':nt' => $notes ?: null, ':u' => $isCust ? null : (int) $user['id']]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE reservations SET res_no = :n WHERE id = :id')->execute([':n' => sprintf('RSV-%04d', $id), ':id' => $id]);
            $pdo->commit();
        } catch (RuntimeException $e) { $pdo->rollBack(); return [0, $e->getMessage()]; }
        Audit::log('create', 'reservation', $id, sprintf('RSV-%04d %s for %d on %s %s', $id, $name, $party, $date, $time));
        return [$id, null];
    }

    public static function setStatus(int $id, string $to, array $user): ?string
    {
        $r = self::find($id);
        if (!$r || !self::canAccess($user, $r)) { return 'Reservation not found.'; }
        if ($r['status'] !== 'Booked') { return 'Only booked reservations can be changed (this one is ' . $r['status'] . ').'; }
        $isCust = $user['role'] === 'customer';
        if ($to === 'Cancelled') {
            if ($isCust && strtotime($r['res_date'] . ' ' . $r['res_time']) < time() + 2 * 3600) { return 'Please call us to cancel within 2 hours of the booking.'; }
        } elseif ($to === 'No-show') {
            if ($isCust) { return 'Not allowed.'; }
            if ($r['res_date'] > date('Y-m-d')) { return 'A future booking cannot be a no-show.'; }
        } else { return 'Invalid status.'; }
        db()->prepare('UPDATE reservations SET status = :s WHERE id = :id')->execute([':s' => $to, ':id' => $id]);
        Audit::log(strtolower($to), 'reservation', $id, $r['res_no'] . ' ' . $to);
        return null;
    }

    /** Guests arrive: open a dine-in order on the table. Returns [orderId, error]. */
    public static function seat(int $id, ?int $tableId, array $user): array
    {
        if (!in_array($user['role'], Auth::FLOOR, true)) { return [0, 'Not allowed.']; }
        $r = self::find($id);
        if (!$r) { return [0, 'Reservation not found.']; }
        if ($r['status'] !== 'Booked') { return [0, 'This reservation is ' . $r['status'] . '.']; }
        if ($r['res_date'] !== date('Y-m-d')) { return [0, 'Only today\'s reservations can be seated.']; }
        $tableId = $tableId ?: (int) $r['table_id'];
        if (!$tableId) { return [0, 'Choose a table to seat the guests.']; }
        [$oid, $err] = OrderModel::open($user, ['type' => 'Dine-in', 'table_id' => $tableId, 'guests' => $r['party_size'], 'customer_id' => $r['customer_id'], 'guest_name' => $r['customer_id'] ? null : $r['guest_name'],
            'guest_phone' => $r['phone'], 'note' => $r['notes'], 'reservation_id' => $id]);
        if ($err) { return [0, $err]; }
        db()->prepare("UPDATE reservations SET status = 'Seated', table_id = :t WHERE id = :id")->execute([':t' => $tableId, ':id' => $id]);
        Audit::log('seat', 'reservation', $id, $r['res_no'] . ' seated');
        return [$oid, null];
    }
}
