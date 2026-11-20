<?php
/** Shared helpers: escaping, URLs, JSON, CSRF, formatting, autoloader. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db_connect.php';

spl_autoload_register(static function (string $class): void {
    foreach ([__DIR__ . '/models/', __DIR__ . '/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return APP_BASE . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function json_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($data) ? $data : [];
}

// ------------------------------------------------------------------ CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_verify(?array $json = null): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ($json['csrf_token'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf'] ?? '', $sent);
}
function require_csrf(?array $json = null): void
{
    if (!csrf_verify($json)) {
        json_response(['success' => false, 'message' => 'Security token expired. Please reload the page.'], 403);
    }
}

// ------------------------------------------------------------- flash msgs
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $msg]; }
function pull_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ------------------------------------------------------------ formatting
function money(float|int|string|null $n): string { return CURRENCY . ' ' . number_format((float) $n, 2); }
function fdate(?string $d, string $fmt = 'd M Y'): string { return $d ? date($fmt, strtotime($d)) : '–'; }
function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

/** Sri Lankan numbers -> international digits for wa.me links (0771234567 -> 94771234567). */
function phone_intl(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (str_starts_with($d, '0')) { $d = '94' . substr($d, 1); }
    return $d;
}

/** Bootstrap colour class for any status badge. */
function status_class(string $s): string
{
    return match ($s) {
        'Draft', 'New', 'Booked', 'Scheduled'                     => 'info',
        'Preparing', 'Partial', 'Billed', 'Cleaning', 'Reserved'  => 'warning',
        'Open', 'Seated', 'Online'                                => 'primary',
        'Completed', 'Paid', 'Available', 'Ready'                 => 'success',
        'Served'                                                  => 'dark',
        'Cancelled', 'No-show', 'Unpaid', 'Occupied', 'Overdue', 'Urgent' => 'danger',
        default                                                   => 'secondary',
    };
}function fdatetime(?string $d): string
{
    return $d ? date('d M Y, H:i', strtotime($d)) : '–';
}

function status_badge(string $s): string
{
    return '<span class="badge badge-soft-' . status_class($s) . '">' . e($s) . '</span>';
}
