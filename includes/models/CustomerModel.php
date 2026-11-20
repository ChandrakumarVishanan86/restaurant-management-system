<?php
declare(strict_types=1);

final class CustomerModel extends BaseModel
{
    protected static string $table = 'customers';
    protected static array $fields = [
        'full_name' => ['label' => 'Full name', 'required' => true, 'max' => 120, 'regex' => "/^[\p{L}][\p{L} .'\-]*$/u", 'regexMsg' => 'Name may contain letters only.'],
        'phone'     => ['label' => 'Phone', 'type' => 'phone', 'required' => true, 'max' => 20, 'unique' => true, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'email'     => ['label' => 'Email', 'type' => 'email', 'max' => 120, 'lower' => true],
        'address'   => ['label' => 'Address', 'max' => 255],
        'birthday'  => ['label' => 'Birthday', 'type' => 'date'],
        'notes'     => ['label' => 'Notes / preferences', 'max' => 255],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];

    protected static function baseSelect(): string
    {
        return "SELECT t.*, (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = t.id AND o.status = 'Paid') AS last_visit,
            (SELECT COUNT(*) FROM users u WHERE u.customer_id = t.id) AS has_login FROM customers t";
    }
    protected static function orderBy(): string { return 'ORDER BY t.total_spent DESC, t.full_name'; }

    public static function scope(array $user): array
    {
        return $user['role'] === 'customer' ? ['t.id = :cid', [':cid' => (int) $user['customer_id']]] : ['', []];
    }
    public static function canAccess(array $user, array $row): bool
    {
        return $user['role'] !== 'customer' || (int) $row['id'] === (int) $user['customer_id'];
    }
    protected static function extraInsert(array $raw): array
    {
        $n = (int) db()->query('SELECT COALESCE(MAX(id),0) FROM customers')->fetchColumn() + 1;
        return ['code' => sprintf('CUS-%04d', $n)];
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        $pdo = db();
        $pdo->prepare('UPDATE users SET full_name = :n, email = :e, phone = :p WHERE customer_id = :id')
            ->execute([':n' => $d['full_name'], ':e' => $d['email'], ':p' => $d['phone'], ':id' => $id]);
        if (!empty($raw['create_login'])) {
            $has = $pdo->prepare('SELECT 1 FROM users WHERE customer_id = :id'); $has->execute([':id' => $id]);
            if (!$has->fetchColumn()) {
                $username = 'c' . preg_replace('/\D+/', '', (string) $d['phone']);
                $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, customer_id) VALUES (:u,:p,:n,:e,:ph,'customer',:id)")
                    ->execute([':u' => $username, ':p' => password_hash(DEFAULT_CUSTOMER_PASSWORD, PASSWORD_DEFAULT), ':n' => $d['full_name'], ':e' => $d['email'], ':ph' => $d['phone'], ':id' => $id]);
            }
        }
        Audit::log($isNew ? 'create' : 'update', 'customer', $id, ($isNew ? 'Added ' : 'Updated ') . $d['full_name']);
    }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT (SELECT COUNT(*) FROM orders WHERE customer_id = :a) + (SELECT COUNT(*) FROM reservations WHERE customer_id = :b)');
        $s->execute([':a' => $id, ':b' => $id]);
        return $s->fetchColumn() > 0 ? 'This customer has orders or reservations. Mark them inactive instead.' : null;
    }
    public static function delete(int $id): bool
    {
        db()->prepare("DELETE FROM users WHERE customer_id = :id AND role = 'customer'")->execute([':id' => $id]);
        return parent::delete($id);
    }

    public static function search(string $term, int $limit = 12): array
    {
        $s = db()->prepare("SELECT id, code, full_name, phone, address, loyalty_points FROM customers WHERE is_active = 1 AND (full_name LIKE :t1 OR code LIKE :t2 OR phone LIKE :t3) ORDER BY full_name LIMIT $limit");
        $l = '%' . $term . '%'; $s->execute([':t1' => $l, ':t2' => $l, ':t3' => $l]);
        return $s->fetchAll();
    }
    public static function favourites(int $id): array
    {
        $s = db()->prepare("SELECT i.name, SUM(i.qty) AS qty FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.customer_id = :id AND i.status <> 'Cancelled' GROUP BY i.menu_item_id, i.name ORDER BY qty DESC LIMIT 5");
        $s->execute([':id' => $id]);
        return $s->fetchAll();
    }
}
