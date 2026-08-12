<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ErrorViewTest extends TestCase
{
    public function testItRendersAnAccessDeniedErrorWithoutAClassResolutionFailure(): void
    {
        $status = 403;
        $message = 'Fail sulit ini memerlukan kebenaran akses daripada Admin.';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Views/error.php';
        $output = (string) ob_get_clean();

        self::assertStringContainsString('403', $output);
        self::assertStringContainsString($message, $output);
    }
}
