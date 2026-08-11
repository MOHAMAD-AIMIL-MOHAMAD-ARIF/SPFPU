<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Http
{
    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = compact('type', 'message');
    }

    public static function old(string $key, string $default = ''): string
    {
        return (string)($_SESSION['old'][$key] ?? $default);
    }

    public static function abort(int $status, string $message): never
    {
        http_response_code($status);
        View::render('error', ['status' => $status, 'message' => $message]);
        exit;
    }
}
