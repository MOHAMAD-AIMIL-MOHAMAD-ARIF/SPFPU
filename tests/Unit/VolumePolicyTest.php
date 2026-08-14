<?php
declare(strict_types=1);

namespace SPFPU\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SPFPU\Core\VolumePolicy;

final class VolumePolicyTest extends TestCase
{
    public function testAdminCanCreateEntriesInOpenAndClosedVolumes(): void
    {
        self::assertTrue(VolumePolicy::canCreateEntry("Admin", "Open"));
        self::assertTrue(VolumePolicy::canCreateEntry("Admin", "Closed"));
    }

    public function testStaffCanCreateEntriesOnlyInOpenVolumes(): void
    {
        self::assertTrue(VolumePolicy::canCreateEntry("Staff", "Open"));
        self::assertFalse(VolumePolicy::canCreateEntry("Staff", "Closed"));
    }

    public function testAdminCanImportOnlyWhenVolumeHasNeverHadAnEntry(): void
    {
        self::assertTrue(VolumePolicy::canImport("Admin", 0));
        self::assertFalse(VolumePolicy::canImport("Admin", 1));
        self::assertFalse(VolumePolicy::canImport("Staff", 0));
    }
}
