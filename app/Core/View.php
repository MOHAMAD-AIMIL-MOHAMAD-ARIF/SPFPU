<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class View
{
    public static function render(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $view = Config::root() . '/app/Views/' . $name . '.php';
        if (!is_file($view)) throw new \RuntimeException("Paparan tidak ditemui: {$name}");
        $user = Auth::user();
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        ob_start();
        require $view;
        $content = (string)ob_get_clean();
        require Config::root() . '/app/Views/layout.php';
        unset($_SESSION['old']);
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function date(?string $value): string
    {
        if (!$value) return '—';
        return (new \DateTimeImmutable($value))->format('d.m.Y');
    }
}
