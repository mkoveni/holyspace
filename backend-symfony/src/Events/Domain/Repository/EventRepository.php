<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventSeries;

interface EventRepository
{
    public function findEventForUpdate(string $id): ?EventDefinition;

    public function findSeriesForUpdate(string $id): ?EventSeries;

    public function insertEvent(EventDefinition $event): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function saveEvent(EventDefinition $event): void;

    public function insertSeries(EventSeries $series): void;

    public function saveSeries(EventSeries $series): void;
}
