<?php

declare(strict_types=1);

namespace App\Communications\Domain\Repository;

use App\Communications\Domain\PushDevice;
use App\Communications\Domain\PushJob;
use DateTimeImmutable;

/** The device push outbox (`communication_push_deliveries`). */
interface PushQueue
{
    /** @return list<string> ids of due QUEUED, RETRY and TICKET jobs, oldest first, at most 25 */
    public function due(DateTimeImmutable $at): array;

    /** Claims a due job (skipping one another worker holds): SENDING with an attempt counted, or CHECKING for a receipt. */
    public function claim(string $id, DateTimeImmutable $at): ?PushJob;

    /** The job's device if it is still active at the queued revision, and the topic still opted in at the queued version. */
    public function eligible(PushJob $job, DateTimeImmutable $at): ?PushDevice;

    /** Records the outcome only if the job is still held by this claim; DeviceNotRegistered retires that device revision. */
    public function finish(PushJob $job, string $state, DateTimeImmutable $due, ?string $ticket, string $error, DateTimeImmutable $at): void;

    /**
     * After a crash: an expired SENDING claim becomes UNKNOWN (never resent), an expired CHECKING claim goes back to
     * TICKET, and devices whose lease ended are revoked.
     */
    public function recover(DateTimeImmutable $at): void;
}
