<?php

declare(strict_types=1);

namespace App\Tests\Communications\Application;

use App\Communications\Application\Command\SetMyNotificationPreference\SetMyNotificationPreferenceCommand;
use App\Communications\Application\Command\SetMyNotificationPreference\SetMyNotificationPreferenceHandler;
use App\Communications\Application\Command\SetMyNotificationRead\SetMyNotificationReadCommand;
use App\Communications\Application\Command\SetMyNotificationRead\SetMyNotificationReadHandler;
use App\Communications\Application\Query\GetMyNotification\GetMyNotificationHandler;
use App\Communications\Application\Query\GetMyNotification\GetMyNotificationQuery;
use App\Communications\Application\Query\GetMyNotificationPreferences\GetMyNotificationPreferencesHandler;
use App\Communications\Application\Query\GetMyNotificationPreferences\GetMyNotificationPreferencesQuery;
use App\Communications\Application\Query\ListMyNotifications\ListMyNotificationsHandler;
use App\Communications\Application\Query\ListMyNotifications\ListMyNotificationsQuery;
use App\Communications\Domain\CommunicationException;
use App\Tests\Communications\Support\ExpiringAccount;
use App\Tests\Communications\Support\RecordingInbox;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * Port of the Java inbox `*HandlerSecurityTest`s: every handler resolves the live account first, so a caller whose
 * session is gone reaches no store, and commands check again after writing so a session revoked mid-way rolls back.
 */
final class MemberInboxHandlersTest extends TestCase
{
    private const string ACCOUNT = '00000000-0000-4000-8000-000000000001';
    private const string NOTICE = '00000000-0000-4000-8000-0000000000aa';

    /** @return array<string, array{Closure(ExpiringAccount, RecordingInbox): mixed, string}> */
    public static function queries(): array
    {
        return [
            'list' => [static fn (ExpiringAccount $a, RecordingInbox $s): mixed => new ListMyNotificationsHandler($a, $s)->handle(new ListMyNotificationsQuery(false, 20, 0)), 'list'],
            'get' => [static fn (ExpiringAccount $a, RecordingInbox $s): mixed => new GetMyNotificationHandler($a, $s)->handle(new GetMyNotificationQuery(self::NOTICE)), 'notification'],
            'preferences' => [static fn (ExpiringAccount $a, RecordingInbox $s): mixed => new GetMyNotificationPreferencesHandler($a, $s)->handle(new GetMyNotificationPreferencesQuery()), 'preferences'],
        ];
    }

    /** @return array<string, array{Closure(ExpiringAccount, RecordingInbox): mixed, string}> */
    public static function commands(): array
    {
        return [
            'read' => [static function (ExpiringAccount $a, RecordingInbox $s): mixed {
                new SetMyNotificationReadHandler($a, $s, new MockClock())->handle(new SetMyNotificationReadCommand(self::NOTICE, true));

                return null;
            }, 'read'],
            'preference' => [static function (ExpiringAccount $a, RecordingInbox $s): mixed {
                new SetMyNotificationPreferenceHandler($a, $s)->handle(new SetMyNotificationPreferenceCommand('EVENTS', true, 0));

                return null;
            }, 'preference'],
        ];
    }

    /** @return array<string, array{Closure(ExpiringAccount, RecordingInbox): mixed, string}> */
    public static function handlers(): array
    {
        return self::queries() + self::commands();
    }

    /** @param Closure(ExpiringAccount, RecordingInbox): mixed $invoke */
    #[DataProvider('handlers')]
    public function testACallerWithoutALiveSessionReachesNoStore(Closure $invoke, string $call): void
    {
        $store = new RecordingInbox();
        $refused = false;
        try {
            $invoke(new ExpiringAccount(self::ACCOUNT, 0), $store);
        } catch (RuntimeException $expected) {
            $refused = 'unauthenticated' === $expected->getMessage();
        }
        self::assertTrue($refused, 'an anonymous or revoked caller is refused');
        self::assertSame([], $store->calls);

        $invoke(new ExpiringAccount(self::ACCOUNT, 2), $store);
        self::assertSame(["$call:".self::ACCOUNT], $store->calls, 'the store is scoped to the live account');
    }

    /** @param Closure(ExpiringAccount, RecordingInbox): mixed $invoke */
    #[DataProvider('commands')]
    public function testACommandRechecksTheSessionAfterWritingSoARevocationRollsBack(Closure $invoke, string $call): void
    {
        $store = new RecordingInbox();
        $refused = false;
        try {
            $invoke(new ExpiringAccount(self::ACCOUNT, 1), $store);
        } catch (RuntimeException $expected) {
            $refused = 'unauthenticated' === $expected->getMessage();
        }
        self::assertTrue($refused, 'the bus rolls the write back when the second check fails');
        self::assertSame(["$call:".self::ACCOUNT], $store->calls);
    }

    public function testAnUnsupportedTopicOrNegativeVersionIsRefusedBeforeTheStore(): void
    {
        foreach ([['UNKNOWN', 0], ['events', 0], ['EVENTS', -1]] as [$topic, $version]) {
            $store = new RecordingInbox();
            try {
                new SetMyNotificationPreferenceHandler(new ExpiringAccount(self::ACCOUNT, 2), $store)->handle(new SetMyNotificationPreferenceCommand($topic, true, $version));
                self::fail("$topic/$version must be refused");
            } catch (CommunicationException $expected) {
                self::assertSame('Choose a supported topic and current preference version.', $expected->getMessage());
            }
            self::assertSame([], $store->calls);
        }
    }
}
