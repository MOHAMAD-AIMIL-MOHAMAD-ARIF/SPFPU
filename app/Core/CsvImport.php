<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class CsvImport
{
    /** @return array{0: ?string, 1: bool} Normalized value and validity. */
    public static function type(string $value): array
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return [null, true];
        }

        $normalized = match ($value) {
            'masuk', 'incoming' => 'Incoming',
            'keluar', 'outgoing' => 'Outgoing',
            default => null,
        };

        return [$normalized, $normalized !== null];
    }

    /** @return array{0: ?string, 1: bool} ISO date (or null) and validity. */
    public static function date(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [null, true];
        }

        foreach (['!d.m.Y', '!j.n.Y', '!Y-m-d', '!d/m/Y', '!j/n/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(substr($format, 1)) === $value) {
                return [$date->format('Y-m-d'), true];
            }
        }

        return [null, false];
    }
}
