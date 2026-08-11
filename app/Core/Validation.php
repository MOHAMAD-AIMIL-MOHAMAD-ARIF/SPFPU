<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Validation
{
    public static function password(string $value): ?string
    {
        if (strlen($value) < 8 || strlen($value) > 19 || !preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value) || !preg_match('/\d/', $value)) {
            return 'Kata laluan mesti 8–19 aksara serta mengandungi huruf besar, huruf kecil dan nombor.';
        }
        return null;
    }

    public static function date(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
