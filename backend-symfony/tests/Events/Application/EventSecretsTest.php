<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\ApproveEventNotification\ApproveEventNotificationCommand;
use App\Events\Application\Command\ScanMemberAttendance\ScanMemberAttendanceCommand;
use App\Events\Application\Contract\ReviewedNotice;
use App\Events\Application\IssuedPass;
use App\Events\Application\Query\ReviewEventNotification\ReviewEventNotificationQuery;
use App\Events\Domain\AdmissionPass;
use App\Events\Domain\EventNotification;
use App\Events\Presentation\Request\ApproveEventNotificationRequest;
use App\Events\Presentation\Request\ReviewEventNotificationRequest;
use App\Events\Presentation\Request\ScanMemberRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Credentials, recipients, message text and evidence must not leak through dumps, logs or stack traces. */
final class EventSecretsTest extends TestCase
{
    private const string SECRET = 'S3CR3T-never-in-a-log';
    private const string ID = '00000000-0000-4000-8000-0000000000c1';

    /** @return iterable<string, array{object}> */
    public static function carriers(): iterable
    {
        $now = new DateTimeImmutable('2026-07-05T10:00:00Z');
        yield 'scan command' => [new ScanMemberAttendanceCommand(self::ID, self::ID, self::ID, 'divinecommunityspace:event:v1:'.self::SECRET)];
        yield 'scan request' => [new ScanMemberRequest(self::ID, 'divinecommunityspace:event:v1:'.self::SECRET)];
        yield 'issued pass' => [new IssuedPass('divinecommunityspace:event:v1:'.self::SECRET, $now)];
        yield 'admission pass' => [new AdmissionPass(self::SECRET, self::ID, self::ID, self::ID, 1, 1, 0, $now)];
        yield 'approve command' => [new ApproveEventNotificationCommand(self::ID, self::ID, self::ID, self::ID, 'REMINDER', self::SECRET, 'hash', self::SECRET)];
        yield 'approve request' => [new ApproveEventNotificationRequest(self::ID, self::ID, 'REMINDER', self::SECRET, 'hash', self::SECRET)];
        yield 'review query' => [new ReviewEventNotificationQuery(self::ID, self::ID, 'REMINDER', self::SECRET)];
        yield 'review request' => [new ReviewEventNotificationRequest(self::ID, 'REMINDER', self::SECRET)];
        yield 'reviewed notice' => [new ReviewedNotice(self::ID, 1, 0, 0, 'REMINDER', self::ID, self::SECRET.'@example.test', self::SECRET, self::SECRET, 'hash', 1)];
        yield 'notification' => [new EventNotification(self::ID, self::ID, 1, 0, 0, 'REMINDER', self::ID, 'hash', 'hash', self::SECRET, self::ID, $now, self::ID)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('carriers')]
    public function testDumpsDoNotContainTheSecret(object $carrier): void
    {
        self::assertStringNotContainsString(self::SECRET, print_r($carrier, true));
        ob_start();
        var_dump($carrier);
        self::assertStringNotContainsString(self::SECRET, (string) ob_get_clean());
    }
}
