<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SPFPU\Core\Csrf;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testMissingSessionAndSubmittedTokensAreRejected(): void
    {
        self::assertFalse(Csrf::isValid($_POST['_token'] ?? null));
    }

    public function testMissingSubmittedTokenIsRejected(): void
    {
        $_SESSION['csrf'] = 'valid-token';

        self::assertFalse(Csrf::isValid($_POST['_token'] ?? null));
    }

    public function testIncorrectSubmittedTokenIsRejected(): void
    {
        $_SESSION['csrf'] = 'valid-token';
        $_POST['_token'] = 'wrong-token';

        self::assertFalse(Csrf::isValid($_POST['_token']));
    }

    public function testArraySubmittedTokenIsRejected(): void
    {
        $_SESSION['csrf'] = 'valid-token';
        $_POST['_token'] = ['valid-token'];

        self::assertFalse(Csrf::isValid($_POST['_token']));
    }

    public function testEmptyTokensAreRejected(): void
    {
        $_SESSION['csrf'] = '';
        $_POST['_token'] = '';

        self::assertFalse(Csrf::isValid($_POST['_token']));
    }

    public function testMatchingNonEmptyTokensAreAccepted(): void
    {
        $_SESSION['csrf'] = 'valid-token';
        $_POST['_token'] = 'valid-token';

        self::assertTrue(Csrf::isValid($_POST['_token']));
    }
}
