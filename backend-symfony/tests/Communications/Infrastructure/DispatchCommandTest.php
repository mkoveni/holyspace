<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Schedule;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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

    public function testTheScheduleRunsTheCommandEveryFifteenSeconds(): void
    {
        $provider = new Schedule(new ArrayAdapter());

        $found = [];
        foreach ($provider->getSchedule()->getRecurringMessages() as $recurring) {
            $context = new MessageContext('default', 'id', $recurring->getTrigger(), new DateTimeImmutable('2026-06-01T10:00:00Z'));
            foreach ($recurring->getMessages($context) as $message) {
                $found[] = $message instanceof \Symfony\Component\Console\Messenger\RunCommandMessage ? $message->input : '';
            }
            self::assertStringContainsString('15 seconds', (string) $recurring->getTrigger());
        }

        self::assertSame(['app:communications:dispatch-due'], $found);
    }
}
