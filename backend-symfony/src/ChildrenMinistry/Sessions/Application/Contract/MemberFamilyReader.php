<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Application\FamilyAttendance;
use App\ChildrenMinistry\Sessions\Application\FamilyEnrolment;
use App\ChildrenMinistry\Sessions\Application\FamilySessionRow;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * Read side of the family views. Access comes only from a guardian's own unrevoked, current authority; households
 * and People relationships never grant it.
 */
interface MemberFamilyReader
{
    /**
     * @return list<string> distinct child ids the guardian has current authority over, by id
     */
    public function children(string $guardianId, DateTimeImmutable $at, int $limit, int $offset): array;

    public function authorized(string $guardianId, string $childId, DateTimeImmutable $at): bool;

    /**
     * Unended enrolments that have not finished by the day, by start.
     *
     * @return list<FamilyEnrolment>
     */
    public function programmes(string $childId, CalendarDate $on, int $limit, int $offset): array;

    /**
     * Sessions overlapping [from, until) of a class the child is placed in on the session's local date.
     *
     * @return list<FamilySessionRow>
     */
    public function sessions(string $childId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array;

    /**
     * Visits since the guardian's earliest current authority for the child began, newest first.
     *
     * @return list<FamilyAttendance>
     */
    public function attendance(string $guardianId, string $childId, DateTimeImmutable $at, int $limit, int $offset): array;
}
