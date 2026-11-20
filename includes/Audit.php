<?php
declare(strict_types=1);

/** Append-only audit trail (who did what). Never throws. */
final class Audit
{
    public static function log(string $action, string $entity, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
    {
        try {
            $uid = $userId ?? (isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null);
            db()->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip) VALUES (:u,:a,:e,:i,:d,:ip)')
                ->execute([':u' => $uid, ':a' => $action, ':e' => $entity, ':i' => $entityId, ':d' => $details !== null ? mb_substr($details, 0, 255) : null, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) { error_log('[audit] ' . $e->getMessage()); }
    }
}
