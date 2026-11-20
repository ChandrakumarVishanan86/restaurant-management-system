<?php
declare(strict_types=1);

/** Staff accounts (non-doctor, non-patient). Customers get portal logins from the Customers page. */
final class UserModel extends BaseModel
{
    public const ROLES = ['admin', 'waiter', 'chef', 'cashier'];
    protected static string $table = 'users';
    protected static array $fields = [
        'username'  => ['label' => 'Username', 'required' => true, 'max' => 50, 'lower' => true, 'unique' => true, 'regex' => '/^[a-z0-9._\-]{3,50}$/', 'regexMsg' => '3-50 chars: a-z, 0-9 . _ -'],
        'full_name' => ['label' => 'Full name', 'required' => true, 'max' => 120],
        'email'     => ['label' => 'Email', 'type' => 'email', 'max' => 120, 'lower' => true, 'unique' => true],
        'phone'     => ['label' => 'Phone', 'max' => 20, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'role'      => ['label' => 'Role', 'type' => 'enum', 'in' => self::ROLES, 'required' => true],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];

    protected static function baseSelect(): string
    {
        return "SELECT t.id, t.username, t.full_name, t.email, t.phone, t.role, t.is_active, t.last_login FROM users t WHERE t.role <> 'customer'";
    }
    public static function find(int $id): ?array
    {
        $s = db()->prepare(static::baseSelect() . ' AND t.id = :id'); $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function all(array $user): array
    {
        return db()->query(static::baseSelect() . " ORDER BY FIELD(t.role,'admin','cashier','waiter','chef'), t.full_name")->fetchAll();
    }

    public static function validate(array $d, int $id = 0): array
    {
        $err = parent::validate($d, $id);
        $pw = (string) ($_POST['password'] ?? '');
        if (($id === 0 || $pw !== '') && strlen($pw) < 8) { $err['password'] = 'Password must be at least 8 characters.'; }
        if ($id > 0 && $id === Auth::id()) {
            if (($d['role'] ?? '') !== Auth::role()) { $err['role'] = 'You cannot change your own role.'; }
            if (empty($d['is_active'])) { $err['is_active'] = 'You cannot deactivate your own account.'; }
        }
        return $err;
    }
    protected static function extraInsert(array $raw): array
    {
        return ['password' => password_hash((string) ($raw['password'] ?? ''), PASSWORD_DEFAULT)];
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        $pw = (string) ($raw['password'] ?? '');
        if (!$isNew && $pw !== '') {
            db()->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([':p' => password_hash($pw, PASSWORD_DEFAULT), ':id' => $id]);
        }
        Audit::log($isNew ? 'create' : 'update', 'user', $id, ($isNew ? 'Created ' : 'Updated ') . $d['username']);
    }
    public static function beforeDelete(int $id): ?string
    {
        if ($id === Auth::id()) { return 'You cannot delete your own account.'; }
        $u = static::find($id);
        if (!$u) { return 'User not found.'; }
        if ($u['role'] === 'admin' && (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn() <= 1) { return 'You cannot delete the last administrator.'; }
        return null;
    }
    public static function delete(int $id): bool
    {
        $s = db()->prepare("DELETE FROM users WHERE id = :id AND role <> 'customer'"); $s->execute([':id' => $id]);
        return $s->rowCount() > 0;
    }
}
