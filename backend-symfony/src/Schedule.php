<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Approved emails go out within about 15 seconds of their scheduled time. Run the worker with:
            // php bin/console messenger:consume scheduler_default
            ->add(RecurringMessage::every('15 seconds', new RunCommandMessage('app:communications:dispatch-due')))
            // Device pushes and their receipts, on the same worker (Java's PUSH_POLL_MS default). Off until PUSH_ENABLED.
            ->add(RecurringMessage::every('15 seconds', new RunCommandMessage('app:communications:dispatch-push')))
            // Pictures replaced or removed are deleted from disk shortly after their change commits.
            ->add(RecurringMessage::every('1 minute', new RunCommandMessage('app:media:delete-pending')))
            // Files no database row records (a crash mid-upload) and stale temporary files, once a day.
            ->add(RecurringMessage::every('1 day', new RunCommandMessage('app:media:gc')))
            // Form responses are hard-deleted when their retention (at most 3 months) ends.
            ->add(RecurringMessage::every('1 day', new RunCommandMessage('app:forms:purge')))
            // Public ticket holds that lapsed without payment free their seats.
            ->add(RecurringMessage::every('1 minute', new RunCommandMessage('app:events:expire-ticket-holds')))
        ;
    }
}
