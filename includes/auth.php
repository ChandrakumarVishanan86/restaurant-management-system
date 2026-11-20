<?php
/**
 * Authentication & role-based access control.
 * Roles: admin | waiter | chef | cashier | customer
 *   Auth::guard(['admin','advisor']);              // page
 *   Auth::guard(['admin'], json: true);            // AJAX endpoint
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

final class Auth
{
    public const STAFF = ['admin', 'waiter', 'chef', 'cashier'];
    public const FLOOR = ['admin', 'waiter', 'cashier'];     // roles that take orders / handle guests
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        session_name('DFSESSID');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => APP_BASE . '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function homeFor(string $role): string { return 'dashboard'; }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return isset($_SESSION['user']['id']); }
    public static function id(): int { return (int) ($_SESSION['user']['id'] ?? 0); }
    public static function role(): string { return $_SESSION['user']['role'] ?? ''; }
    public static function hasRole(string ...$roles): bool { return self::check() && in_array(self::role(), $roles, true); }
    public static function isStaff(): bool { return self::hasRole(...self::STAFF); }

    public static function guard(array $roles = [], bool $json = false): array
    {
        if (!self::check()) {
            if ($json) { json_response(['success' => false, 'message' => 'Session expired. Please sign in again.'], 401); }
            redirect('login');
        }
        $user = self::user();
        if ($roles && !in_array($user['role'], $roles, true)) {
            if ($json) { json_response(['success' => false, 'message' => 'You do not have permission for this action.'], 403); }
            flash('danger', 'You do not have permission to open that page.');
            redirect(self::homeFor($user['role']));
        }
        return $user;
    }

    public static function lockedFor(): int { return max(0, ($_SESSION['login_lock_until'] ?? 0) - time()); }

    public static function attempt(string $username, string $password): ?array
    {
        if (self::lockedFor() > 0) { return null; }

        $stmt = db()->prepare('SELECT * FROM users WHERE username = :u AND is_active = 1 LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password'])) {
            $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_lock_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['login_fails'] = 0;
            }
            return null;
        }
        if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['id']]);
        }

        session_regenerate_id(true);
        unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
        $_SESSION['user'] = [
            'id' => (int) $row['id'], 'username' => $row['username'], 'full_name' => $row['full_name'],
            'role' => $row['role'], 'customer_id' => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
        ];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id' => $row['id']]);
        Audit::log('login', 'user', (int) $row['id'], $row['full_name'] . ' signed in', (int) $row['id']);
        return $_SESSION['user'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}

Auth::start();
