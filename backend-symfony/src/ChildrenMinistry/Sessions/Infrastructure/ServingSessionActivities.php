<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\Volunteering\Application\Contract\ActivitySource;
use App\Volunteering\Application\Contract\SourceActivity;
use DateTimeImmutable;

/** Publishes children's sessions to Volunteering as activities that can be staffed. */
final readonly class ServingSessionActivities implements ActivitySource
{
    public const string KIND = 'CHILDREN_SESSION';

    public function __construct(private SessionReader $sessions, private ParticipationReader $participation)
    {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function find(string $id): ?SourceActivity
    {
        $session = $this->sessions->ministrySession($id);

        return null === $session ? null : $this->activity($session);
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit = null, int $offset = 0): array
    {
        $active = array_values(array_filter(
            array_map($this->activity(...), $this->sessions->sessions(null, $from, $until, null, 0)),
            static fn (SourceActivity $activity): bool => $activity->active,
        ));

        return array_slice($active, $offset, $limit);
    }

    private function activity(MinistrySession $session): SourceActivity
    {
        $class = $this->participation->ministryClass($session->classId);
        $label = null === $class ? 'Children session' : $class->name;

        return new SourceActivity($session->id, $label, $session->startsAt, $session->endsAt, !$session->status->terminal());
    }
}
