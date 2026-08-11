<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SPFPU\Core\CsvImport;

final class CsvImportTest extends TestCase
{
    public function testBlankOptionalValuesAreValid(): void
    {
        self::assertSame([null, true], CsvImport::type(''));
        self::assertSame([null, true], CsvImport::date(''));
        self::assertSame([null, true], CsvImport::date('   '));
    }

    public function testNonblankValuesAreStillValidated(): void
    {
        self::assertSame(['Incoming', true], CsvImport::type('Masuk'));
        self::assertSame([null, false], CsvImport::type('Unknown'));
        self::assertSame(['2026-08-11', true], CsvImport::date('11.08.2026'));
        self::assertSame([null, false], CsvImport::date('31.02.2026'));
    }
}
