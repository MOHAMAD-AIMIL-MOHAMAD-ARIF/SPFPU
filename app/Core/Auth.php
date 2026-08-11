<?php
declare(strict_types=1);

namespace SPFPU\Core;

use PDO;

final class Auth
{
    private static ?array $cached = null;

    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) return null;
        if (self::$cached) return self::$cached;
        $stmt = Database::connection()->prepare('SELECT id, fullname, username, email, phone, role, status, reset_warning FROM users WHERE id=?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'Active') {
            self::logout();
            return null;
        }
        return self::$cached = $user;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) Http::redirect('/login');
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'Admin') Http::abort(403, 'Tindakan ini hanya dibenarkan untuk Admin.');
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        self::$cached = null;
    }

    public static function logout(): void
    {
        self::$cached = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function canAccessFolder(int $folderId): bool
    {
        $user = self::requireLogin();
        if ($user['role'] === 'Admin') return true;
        $stmt = Database::connection()->prepare('SELECT 1 FROM folders f LEFT JOIN folder_access fa ON fa.folder_id=f.id AND fa.user_id=? WHERE f.id=? AND f.archived_at IS NULL AND (f.is_confidential=0 OR fa.user_id IS NOT NULL)');
        $stmt->execute([$user['id'], $folderId]);
        return (bool)$stmt->fetchColumn();
    }
}
