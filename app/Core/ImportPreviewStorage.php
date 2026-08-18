<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class ImportPreviewStorage
{
    /**
     * @param list<array{token: string, temp_path: string}> $previews
     * @param callable(string): void $deleteRecord
     * @param null|callable(string): bool $removeFile
     */
    public static function purge(
        array $previews,
        callable $deleteRecord,
        ?callable $removeFile = null
    ): int {
        $removeFile ??= static fn(string $path): bool =>
            !is_file($path) || @unlink($path);

        $removed = 0;
        foreach ($previews as $preview) {
            if (!$removeFile($preview["temp_path"])) {
                continue;
            }
            $deleteRecord($preview["token"]);
            $removed++;
        }
        return $removed;
    }

    /** @param list<string> $knownPaths */
    public static function purgeOrphans(
        string $directory,
        array $knownPaths,
        int $olderThan
    ): int {
        $root = realpath($directory);
        if ($root === false) {
            return 0;
        }
        $known = [];
        foreach ($knownPaths as $path) {
            $known[self::normalizePath($path)] = true;
        }

        $removed = 0;
        foreach (glob($root . DIRECTORY_SEPARATOR . "*.json") ?: [] as $path) {
            if (
                !preg_match('/^[a-f0-9]{64}\.json$/', basename($path)) ||
                isset($known[self::normalizePath($path)]) ||
                filemtime($path) === false ||
                filemtime($path) >= $olderThan
            ) {
                continue;
            }
            if (@unlink($path)) {
                $removed++;
            }
        }
        return $removed;
    }

    private static function normalizePath(string $path): string
    {
        $resolved = realpath($path);
        return strtolower(str_replace("\\", "/", $resolved ?: $path));
    }
}
