<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\ValueObject\EventId;

interface EventReadStore
{
    public function find(EventId $id): ?ChurchEvent;

    /** @return list<ChurchEvent> */
    public function all(): array;
}
