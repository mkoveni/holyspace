<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Query\GetLifeEvent;

use App\LifeEvents\Application\DTO\LifeEventView;
use App\LifeEvents\Application\ReadModel\LifeEventReadStore;
use App\LifeEvents\Domain\Exception\LifeEventNotFound;
use App\LifeEvents\Domain\ValueObject\LifeEventId;

final readonly class GetLifeEventHandler
{
    public function __construct(private LifeEventReadStore $lifeEvents)
    {
    }

    public function __invoke(GetLifeEventQuery $query): LifeEventView
    {
        $e = $this->lifeEvents->findById(LifeEventId::fromString($query->lifeEventId));
        if (!$e) {
            throw LifeEventNotFound::withId($query->lifeEventId);
        }

        return LifeEventView::fromDomain($e);
    }
}
