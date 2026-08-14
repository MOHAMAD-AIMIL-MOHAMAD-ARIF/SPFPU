<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class VolumePolicy
{
    public static function canCreateEntry(string $role, string $status): bool
    {
        return $role === "Admin" || $status === "Open";
    }

    public static function canImport(string $role, int $entryCount): bool
    {
        return $role === "Admin" && $entryCount === 0;
    }
}
