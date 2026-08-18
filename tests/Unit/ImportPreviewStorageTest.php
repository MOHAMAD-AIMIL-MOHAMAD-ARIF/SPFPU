<?php
declare(strict_types=1);

namespace SPFPU\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SPFPU\Core\ImportPreviewStorage;

final class ImportPreviewStorageTest extends TestCase
{
    public function testPurgeRemovesFileBeforeDeletingRecord(): void
    {
        $path = tempnam(sys_get_temp_dir(), "spfpu-preview-");
        self::assertIsString($path);
        file_put_contents($path, '{"entry_no":1}');
        $deleted = [];

        $removed = ImportPreviewStorage::purge(
            [["token" => "preview-token", "temp_path" => $path]],
            static function (string $token) use (&$deleted, $path): void {
                self::assertFileDoesNotExist($path);
                $deleted[] = $token;
            }
        );

        self::assertSame(1, $removed);
        self::assertSame(["preview-token"], $deleted);
    }

    public function testPurgeKeepsRecordWhenFileRemovalFails(): void
    {
        $deleted = [];
        $removed = ImportPreviewStorage::purge(
            [["token" => "retry-token", "temp_path" => "preview.json"]],
            static function (string $token) use (&$deleted): void {
                $deleted[] = $token;
            },
            static fn(string $path): bool => false
        );

        self::assertSame(0, $removed);
        self::assertSame([], $deleted);
    }

    public function testPurgeDeletesRecordWhenFileIsAlreadyMissing(): void
    {
        $deleted = [];
        $removed = ImportPreviewStorage::purge(
            [["token" => "missing-token", "temp_path" => sys_get_temp_dir() . "/missing-spfpu-preview.json"]],
            static function (string $token) use (&$deleted): void {
                $deleted[] = $token;
            }
        );

        self::assertSame(1, $removed);
        self::assertSame(["missing-token"], $deleted);
    }

    public function testPurgeOrphansOnlyRemovesOldUnreferencedTokenFiles(): void
    {
        $directory = sys_get_temp_dir() . "/spfpu-previews-" . bin2hex(random_bytes(6));
        mkdir($directory);
        $orphan = $directory . "/" . str_repeat("a", 64) . ".json";
        $known = $directory . "/" . str_repeat("b", 64) . ".json";
        $recent = $directory . "/" . str_repeat("c", 64) . ".json";
        $unrelated = $directory . "/notes.json";
        foreach ([$orphan, $known, $recent, $unrelated] as $path) {
            file_put_contents($path, "{}");
        }
        touch($orphan, time() - 3600);
        touch($known, time() - 3600);
        touch($unrelated, time() - 3600);

        try {
            $removed = ImportPreviewStorage::purgeOrphans(
                $directory,
                [$known],
                time() - 1800
            );

            self::assertSame(1, $removed);
            self::assertFileDoesNotExist($orphan);
            self::assertFileExists($known);
            self::assertFileExists($recent);
            self::assertFileExists($unrelated);
        } finally {
            foreach ([$orphan, $known, $recent, $unrelated] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }
}
