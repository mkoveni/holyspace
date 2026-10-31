<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Query\ListLifeEvents;

use App\LifeEvents\Application\DTO\LifeEventView;
use App\LifeEvents\Application\ReadModel\LifeEventReadStore;
use App\LifeEvents\Domain\Enum\LifeEventType;

final readonly class ListLifeEventsHandler
{
    public function __construct(private LifeEventReadStore $lifeEvents)
    {
    }

    /** @return list<LifeEventView> */
    public function __invoke(ListLifeEventsQuery $query): array
    {
        $events = null === $query->type ? $this->lifeEvents->findAll() : $this->lifeEvents->findByType(LifeEventType::from($query->type));
        $events = array_filter($events, static function ($event) use ($query): bool {
            $date = $event->eventDate();
            if (null !== $query->month && (int) $date->format('n') !== $query->month) {
                return false;
            }
            if ($query->anniversaries) {
                return 'cancelled' !== $event->status()->value && (null === $query->year || (int) $date->format('Y') <= $query->year);
            }

            return null === $query->year || (int) $date->format('Y') === $query->year;
        });

        return array_values(array_map(static fn ($event) => LifeEventView::fromDomain($event), $events));
    }
}
