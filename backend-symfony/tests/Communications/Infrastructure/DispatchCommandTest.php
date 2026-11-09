<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Schedule;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class DispatchCommandTest extends DatabaseTestCase
{
    public function testTheCommandReportsNothingToDoWhileSendingIsDisabled(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:communications:dispatch-due'));

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Attempted 0 due deliveries.', $tester->getDisplay());
    }

    public function testThePushCommandReportsHowManyJobsItAttempted(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:communications:dispatch-push'));

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Attempted 0 due push jobs.', $tester->getDisplay());
    }

    public function testTheScheduleRunsTheEmailAndPushWorkersEveryFifteenSeconds(): void
    {
        $communications = array_filter(self::scheduled(), static fn (string $command): bool => str_starts_with($command, 'app:communications:'), ARRAY_FILTER_USE_KEY);

        self::assertSame(['app:communications:dispatch-due', 'app:communications:dispatch-push'], array_keys($communications));
        foreach ($communications as $trigger) {
            self::assertStringContainsString('15 seconds', $trigger);
        }
    }

    public function testTheScheduleDeletesReplacedPicturesEveryMinuteAndSweepsOrphansDaily(): void
    {
        $media = array_filter(self::scheduled(), static fn (string $command): bool => str_starts_with($command, 'app:media:'), ARRAY_FILTER_USE_KEY);

        self::assertSame(['app:media:delete-pending', 'app:media:gc'], array_keys($media));
        self::assertStringContainsString('1 minute', $media['app:media:delete-pending']);
        self::assertStringContainsString('1 day', $media['app:media:gc']);
    }

    /** @return array<string, string> scheduled console command => its trigger */
    private static function scheduled(): array
    {
        $found = [];
        foreach (new Schedule(new ArrayAdapter())->getSchedule()->getRecurringMessages() as $recurring) {
            $context = new MessageContext('default', 'id', $recurring->getTrigger(), new DateTimeImmutable('2026-06-01T10:00:00Z'));
            foreach ($recurring->getMessages($context) as $message) {
                $found[$message instanceof RunCommandMessage ? $message->input : ''] = (string) $recurring->getTrigger();
            }
        }

        return $found;
    }
}
