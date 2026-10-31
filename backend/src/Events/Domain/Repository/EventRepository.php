<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\ValueObject\EventId;

interface EventRepository
{
    public function save(ChurchEvent $event): void;

    public function find(EventId $id): ?ChurchEvent;

    /** @return list<ChurchEvent> */
    public function all(): array;
}
