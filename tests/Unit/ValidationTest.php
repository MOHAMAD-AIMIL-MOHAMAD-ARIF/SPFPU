<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SPFPU\Core\Validation;

final class ValidationTest extends TestCase
{
    public function testPasswordPolicy():void
    {
        self::assertNull(Validation::password('Selamat123'));
        self::assertNotNull(Validation::password('semuaabc1'));
        self::assertNotNull(Validation::password('SEMUAABC1'));
        self::assertNotNull(Validation::password('TanpaNombor'));
        self::assertNotNull(Validation::password('A1bc'));
    }

    public function testRealCalendarDates():void
    {
        self::assertTrue(Validation::date('2024-02-29'));
        self::assertFalse(Validation::date('2025-02-29'));
        self::assertFalse(Validation::date('31.12.2025'));
    }
}
