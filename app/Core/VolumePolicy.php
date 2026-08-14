<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class VolumePolicy
{
    public const MIN_SEQUENCE = 1;
    public const MAX_SEQUENCE = 200;

    public static function canCreateEntry(string $role, string $status): bool
    {
        return $role === "Admin" || $status === "Open";
    }

    public static function canImport(string $role, int $entryCount): bool
    {
        return $role === "Admin" && $entryCount === 0;
    }

    public static function canRenumber(string $role, int $folderEntryCount): bool
    {
        return $role === "Admin" && $folderEntryCount === 0;
    }

    public static function isResultingRangeValid(int $firstSequence, int $volumeCount): bool
    {
        return $volumeCount > 0 &&
            $firstSequence >= self::MIN_SEQUENCE &&
            $firstSequence + $volumeCount - 1 <= self::MAX_SEQUENCE;
    }
}
