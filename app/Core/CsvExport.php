<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class CsvExport
{
    /** @return list<string> */
    public static function row(array $values): array
    {
        return array_map(self::cell(...), array_values($values));
    }

    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Spreadsheet applications may interpret these leading characters as formulas.
        if (preg_match('/\A[=+\-@\t\r\n\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
