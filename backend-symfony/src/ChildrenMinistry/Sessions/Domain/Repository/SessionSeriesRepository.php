<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain\Repository;

use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\Shared\Domain\CalendarDate;

/** Write side of recurring series. Series changes serialise on one scheduling lock before any row lock. */
interface SessionSeriesRepository
{
    /** Takes the single scheduling guard row; held until the transaction ends. */
    public function lockScheduling(): void;

    /** The series this actor created with this request id, for idempotent retries. */
    public function request(string $actorId, string $requestId): ?SessionSeries;

    public function forUpdate(string $id): ?SessionSeries;

    public function insert(SessionSeries $series): void;

    /** Updates the row only if its stored version is one less than the series'; otherwise a 409. */
    public function update(SessionSeries $series): void;

    public function link(string $seriesId, string $sessionId, CalendarDate $date): void;

    /** @return list<string> */
    public function sessionIds(string $seriesId): array;
}
