<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SPFPU\Core\Auth;

final class AuthVersionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testMatchingIntegerVersionsAreCurrent(): void
    {
        $_SESSION['auth_version'] = 7;

        self::assertTrue(Auth::hasCurrentAuthVersion(['auth_version' => 7]));
        self::assertTrue(Auth::hasCurrentAuthVersion(['auth_version' => '7']));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginStoresTheCurrentAuthVersionInTheSession(): void
    {
        session_id('spfpu-auth-' . bin2hex(random_bytes(8)));
        session_start();

        try {
            Auth::login(['id' => 42, 'auth_version' => 9]);

            self::assertSame(42, $_SESSION['user_id']);
            self::assertSame(9, $_SESSION['auth_version']);
            self::assertArrayHasKey('last_activity', $_SESSION);
            self::assertArrayHasKey('csrf', $_SESSION);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        }
    }

    public static function staleOrInvalidVersions(): iterable
    {
        yield 'missing session version' => [[], ['auth_version' => 1]];
        yield 'missing stored version' => [['auth_version' => 1], []];
        yield 'older session' => [['auth_version' => 1], ['auth_version' => 2]];
        yield 'newer session' => [['auth_version' => 3], ['auth_version' => 2]];
        yield 'string session version' => [['auth_version' => '2'], ['auth_version' => 2]];
        yield 'malformed stored version' => [['auth_version' => 2], ['auth_version' => 'current']];
    }

    #[DataProvider('staleOrInvalidVersions')]
    public function testStaleOrInvalidVersionsAreRejected(array $session, array $user): void
    {
        $_SESSION = $session;

        self::assertFalse(Auth::hasCurrentAuthVersion($user));
    }

    public function testInvalidateClearsAuthenticationAndCreatesANewCsrfToken(): void
    {
        $_SESSION = [
            'user_id' => 42,
            'auth_version' => 3,
            'last_activity' => time(),
            'csrf' => 'old-token',
        ];

        Auth::invalidate();

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertArrayNotHasKey('auth_version', $_SESSION);
        self::assertArrayNotHasKey('last_activity', $_SESSION);
        self::assertIsString($_SESSION['csrf']);
        self::assertNotSame('old-token', $_SESSION['csrf']);
    }
}
