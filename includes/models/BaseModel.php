<?php
declare(strict_types=1);

/**
 * Tiny active-record style base: every subclass declares its table and a
 * field schema; validation, uniqueness checks and INSERT/UPDATE are generic
 * and always use PDO prepared statements.
 *
 * Field schema keys: label, type (string|text|email|phone|int|decimal|date|enum|bool|ref),
 * required, max, min, in[], unique, regex, regexMsg, upper, lower, ref[table], default
 */
abstract class BaseModel
{
    protected static string $table = '';
    protected static array $fields = [];
    protected static array $noUpdate = [];      // columns that cannot change through the edit form

    public static function fields(): array { return static::$fields; }
    protected static function baseSelect(): string { return 'SELECT t.* FROM ' . static::$table . ' t'; }
    protected static function orderBy(): string { return 'ORDER BY t.id DESC'; }

    /** [sql fragment, params] restricting rows for this user (default: none). */
    public static function scope(array $user): array { return ['', []]; }
    public static function canAccess(array $user, array $row): bool { return true; }

    public static function all(array $user): array
    {
        [$where, $params] = static::scope($user);
        $sql = static::baseSelect() . ($where ? " WHERE $where" : '') . ' ' . static::orderBy();
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(static::baseSelect() . ' WHERE t.id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Pull only declared fields out of raw input and normalise them. */
    public static function clean(array $in): array
    {
        $d = [];
        foreach (static::$fields as $name => $f) {
            $raw = $in[$name] ?? null;
            $type = $f['type'] ?? 'string';
            if ($type === 'bool') { $d[$name] = !empty($raw) && $raw !== '0' && $raw !== 'false' ? 1 : 0; continue; }
            $v = is_string($raw) ? trim($raw) : $raw;
            if ($v === '' || $v === null) { $d[$name] = null; continue; }
            if (!empty($f['upper'])) { $v = strtoupper((string) $v); }
            if (!empty($f['lower'])) { $v = strtolower((string) $v); }
            $d[$name] = $v;
        }
        return $d;
    }

    public static function validate(array $d, int $id = 0): array
    {
        $err = [];
        foreach (static::$fields as $name => $f) {
            $label = $f['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $type = $f['type'] ?? 'string';
            $v = $d[$name] ?? null;
            if ($id > 0 && in_array($name, static::$noUpdate, true)) { continue; }

            if ($v === null) {
                if (!empty($f['required'])) { $err[$name] = "$label is required."; }
                continue;
            }
            switch ($type) {
                case 'email':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) { $err[$name] = "Enter a valid $label."; }
                    break;
                case 'int':
                    if (!is_numeric($v) || floor((float) $v) != (float) $v) { $err[$name] = "$label must be a whole number."; }
                    break;
                case 'decimal':
                    if (!is_numeric($v)) { $err[$name] = "$label must be a number."; }
                    break;
                case 'date':
                    if (!valid_date((string) $v)) { $err[$name] = "$label must be a valid date."; }
                    break;
                case 'enum':
                    if (!in_array($v, $f['in'] ?? [], true)) { $err[$name] = "Invalid $label."; }
                    break;
                case 'ref':
                    $s = db()->prepare('SELECT 1 FROM ' . $f['ref'] . ' WHERE id = :id');
                    $s->execute([':id' => (int) $v]);
                    if (!$s->fetchColumn()) { $err[$name] = "Choose a valid $label."; }
                    break;
            }
            if (isset($err[$name])) { continue; }
            if (in_array($type, ['int', 'decimal'], true)) {
                if (isset($f['min']) && $v < $f['min']) { $err[$name] = "$label must be at least {$f['min']}."; continue; }
                if (isset($f['max']) && $v > $f['max']) { $err[$name] = "$label must be at most {$f['max']}."; continue; }
            } elseif (isset($f['max']) && mb_strlen((string) $v) > $f['max']) {
                $err[$name] = "$label is too long (max {$f['max']})."; continue;
            }
            if (!empty($f['regex']) && !preg_match($f['regex'], (string) $v)) {
                $err[$name] = $f['regexMsg'] ?? "$label is not in a valid format."; continue;
            }
            if (!empty($f['unique']) && static::exists($name, $v, $id)) {
                $err[$name] = "This $label is already in use.";
            }
        }
        return $err;
    }

    public static function exists(string $field, mixed $value, int $excludeId = 0): bool
    {
        if (!isset(static::$fields[$field])) { return false; }
        $stmt = db()->prepare('SELECT 1 FROM ' . static::$table . " WHERE `$field` = :v AND id <> :id LIMIT 1");
        $stmt->execute([':v' => $value, ':id' => $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Hooks */
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void {}
    /** Extra column => value pairs written only on INSERT (e.g. a hashed password). */
    protected static function extraInsert(array $raw): array { return []; }
    public static function beforeDelete(int $id): ?string { return null; }

    public static function save(array $d, int $id = 0, array $raw = []): int
    {
        $pdo = db();
        $isNew = $id === 0;
        $cols = array_keys(array_filter(static::$fields, static fn($f, $k) => !array_key_exists('virtual', $f), ARRAY_FILTER_USE_BOTH));
        if (!$isNew) { $cols = array_values(array_diff($cols, static::$noUpdate)); }

        $pdo->beginTransaction();
        try {
            $params = [];
            foreach ($cols as $c) { $params[":$c"] = $d[$c] ?? (static::$fields[$c]['default'] ?? null); }
            if ($isNew) {
                foreach (static::extraInsert($raw) as $c => $v) { $cols[] = $c; $params[":$c"] = $v; }
                $sql = 'INSERT INTO ' . static::$table . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_keys($params)) . ')';
                $pdo->prepare($sql)->execute($params);
                $id = (int) $pdo->lastInsertId();
            } else {
                $set = implode(', ', array_map(static fn($c) => "`$c` = :$c", $cols));
                $params[':__id'] = $id;
                $pdo->prepare('UPDATE ' . static::$table . " SET $set WHERE id = :__id")->execute($params);
            }
            static::afterSave($id, $d, $isNew, $raw);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM ' . static::$table . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
