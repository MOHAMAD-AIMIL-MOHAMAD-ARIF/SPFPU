<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Config
{
    private static array $values = [];

    public static function load(string $root): void
    {
        $file = $root . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                self::$values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
        date_default_timezone_set('Asia/Kuala_Lumpur');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value !== false ? $value : (self::$values[$key] ?? $default);
    }

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
