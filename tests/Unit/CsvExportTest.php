<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SPFPU\Core\CsvExport;

final class CsvExportTest extends TestCase
{
    public static function formulaPrefixes(): iterable
    {
        yield 'equals' => ['=HYPERLINK("https://example.test")'];
        yield 'plus' => ['+SUM(1,1)'];
        yield 'minus' => ['-2+3'];
        yield 'at sign' => ['@SUM(1,1)'];
        yield 'tab' => ["\t=SUM(1,1)"];
        yield 'carriage return' => ["\r=SUM(1,1)"];
        yield 'line feed' => ["\n=SUM(1,1)"];
        yield 'full-width equals' => ['＝SUM(1,1)'];
        yield 'full-width plus' => ['＋SUM(1,1)'];
        yield 'full-width minus' => ['－2+3'];
        yield 'full-width at sign' => ['＠SUM(1,1)'];
    }

    #[DataProvider('formulaPrefixes')]
    public function testFormulaPrefixesAreExportedAsText(string $value): void
    {
        self::assertSame("'" . $value, CsvExport::cell($value));
    }

    public function testOrdinaryCellsRemainUnchanged(): void
    {
        self::assertSame(
            ['Kategori', 'Jilid 1', '42', '', "O'Reilly"],
            CsvExport::row(['Kategori', 'Jilid 1', 42, null, "O'Reilly"])
        );
    }

    public function testEveryCellInAnExportRowIsNeutralized(): void
    {
        self::assertSame(
            ["'=category", "'+reference", "'-name", "'@matter"],
            CsvExport::row(['=category', '+reference', '-name', '@matter'])
        );
    }

    public function testNeutralizationSurvivesCsvSerialization(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        fputcsv($stream, CsvExport::row(['=SUM(1,1)', "\t=SUM(1,1)", 'ordinary']));
        rewind($stream);

        self::assertSame(
            ["'=SUM(1,1)", "'\t=SUM(1,1)", 'ordinary'],
            fgetcsv($stream)
        );
        fclose($stream);
    }
}
