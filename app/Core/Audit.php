<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Audit
{
    public static function log(string $action, string $targetType, ?int $targetId = null, ?array $before = null, ?array $after = null, ?int $actorId = null): void
    {
        $sanitize = static function (?array $data): ?string {
            if ($data === null) return null;
            foreach (array_keys($data) as $key) {
                if (preg_match('/password|hash|token/i', (string)$key)) unset($data[$key]);
            }
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };
        $stmt = Database::connection()->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, ip_address, before_values, after_values, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$actorId ?? (Auth::user()['id'] ?? null), $action, $targetType, $targetId, substr($_SERVER['REMOTE_ADDR'] ?? 'CLI', 0, 45), $sanitize($before), $sanitize($after)]);
    }
}
