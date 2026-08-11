<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . View::e(self::token()) . '">';
    }

    public static function verify(): void
    {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_token'] ?? ''))) {
            Http::abort(419, 'Sesi borang telah tamat. Sila cuba semula.');
        }
    }
}
