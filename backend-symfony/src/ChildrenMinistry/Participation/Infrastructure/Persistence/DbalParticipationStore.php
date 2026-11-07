<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Infrastructure\Persistence;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Participation\Domain\Programme;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalParticipationStore implements ParticipationRepository, ParticipationReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function insertProgramme(Programme $programme): void
    {
        $this->connection->insert('children_programmes', [
            'id' => $programme->id,
            'name' => $programme->name,
            'current_policy_id' => $programme->currentPolicyId,
            'min_staff' => $programme->minStaff,
            'children_per_staff' => $programme->childrenPerStaff,
            'version' => $programme->version,
            'created_at' => Timestamps::toDatabase($programme->createdAt),
        ]);
    }

    public function programmeForUpdate(string $id): ?Programme
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_programmes')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $view = self::programmeView($row);

        return Programme::reconstitute($view->id, $view->name, $view->currentPolicyId, $view->minStaff, $view->childrenPerStaff, $view->version, $view->createdAt);
    }

    public function updateProgramme(Programme $programme): void
    {
        $changed = $this->connection->update(
            'children_programmes',
            ['name' => $programme->name, 'current_policy_id' => $programme->currentPolicyId, 'min_staff' => $programme->minStaff, 'children_per_staff' => $programme->childrenPerStaff, 'version' => $programme->version],
            ['id' => $programme->id, 'version' => $programme->version - 1],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function insertClass(MinistryClass $class): void
    {
        $this->connection->insert('children_classes', [
            'id' => $class->id,
            'programme_id' => $class->programmeId,
            'name' => $class->name,
            'min_age_months' => $class->minAgeMonths,
            'max_age_months' => $class->maxAgeMonths,
        ]);
    }

    public function findClass(string $id): ?MinistryClass
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_classes')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : MinistryClass::reconstitute((string) $row['id'], (string) $row['programme_id'], (string) $row['name'], (int) $row['min_age_months'], (int) $row['max_age_months']);
    }

    public function enrolmentForUpdate(string $id): ?Enrolment
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_enrolments')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : Enrolment::reconstitute(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['child_id'],
            CalendarDate::parse(substr((string) $row['valid_from'], 0, 10)),
            CalendarDate::parse(substr((string) $row['valid_until'], 0, 10)),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function insertEnrolment(Enrolment $enrolment): void
    {
        $this->connection->insert('children_enrolments', [
            'id' => $enrolment->id,
            'programme_id' => $enrolment->programmeId,
            'child_id' => $enrolment->childId,
            'valid_from' => $enrolment->validFrom->iso(),
            'valid_until' => $enrolment->validUntil->iso(),
            'ended_at' => null === $enrolment->endedAt ? null : Timestamps::toDatabase($enrolment->endedAt),
            'version' => $enrolment->version,
            'created_at' => Timestamps::toDatabase($enrolment->createdAt),
        ]);
    }

    public function updateEnrolment(Enrolment $enrolment): void
    {
        $changed = $this->connection->update(
            'children_enrolments',
            [
                'valid_from' => $enrolment->validFrom->iso(),
                'valid_until' => $enrolment->validUntil->iso(),
                'ended_at' => null === $enrolment->endedAt ? null : Timestamps::toDatabase($enrolment->endedAt),
                'version' => $enrolment->version,
            ],
            ['id' => $enrolment->id, 'version' => $enrolment->version - 1],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function endAssignments(string $enrolmentId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE children_class_assignments SET ended_at = ?, version = version + 1 WHERE enrolment_id = ? AND ended_at IS NULL',
            [Timestamps::toDatabase($at), $enrolmentId],
        );
    }

    public function classAssignmentForUpdate(string $id): ?ClassAssignment
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_class_assignments')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : ClassAssignment::reconstitute(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['class_id'],
            (string) $row['child_id'],
            (string) $row['enrolment_id'],
            CalendarDate::parse(substr((string) $row['valid_from'], 0, 10)),
            CalendarDate::parse(substr((string) $row['valid_until'], 0, 10)),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
        );
    }

    public function insertClassAssignment(ClassAssignment $assignment): void
    {
        $this->connection->insert('children_class_assignments', [
            'id' => $assignment->id,
            'programme_id' => $assignment->programmeId,
            'class_id' => $assignment->classId,
            'child_id' => $assignment->childId,
            'enrolment_id' => $assignment->enrolmentId,
            'valid_from' => $assignment->validFrom->iso(),
            'valid_until' => $assignment->validUntil->iso(),
            'ended_at' => null === $assignment->endedAt ? null : Timestamps::toDatabase($assignment->endedAt),
            'version' => $assignment->version,
        ]);
    }

    public function updateClassAssignment(ClassAssignment $assignment): void
    {
        $changed = $this->connection->update(
            'children_class_assignments',
            [
                'valid_from' => $assignment->validFrom->iso(),
                'valid_until' => $assignment->validUntil->iso(),
                'ended_at' => null === $assignment->endedAt ? null : Timestamps::toDatabase($assignment->endedAt),
                'version' => $assignment->version,
            ],
            ['id' => $assignment->id, 'version' => $assignment->version - 1],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function programme(string $id): ?ProgrammeView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_programmes')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::programmeView($row);
    }

    public function programmes(string $search, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_programmes')
            ->where('name ILIKE :pattern')
            ->setParameter('pattern', LikePattern::contains($search))
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::programmeView(...), $rows));
    }

    public function ministryClass(string $id): ?ClassView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_classes')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::classView($row);
    }

    public function classes(string $programmeId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_classes')
            ->where('programme_id = :programme')
            ->setParameter('programme', $programmeId)
            ->orderBy('name')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_values(array_map(self::classView(...), $rows));
    }

    public function enrolment(string $id): ?EnrolmentView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_enrolments')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::enrolmentView($row);
    }

    public function classAssignment(string $id): ?AssignmentView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('children_class_assignments')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::assignmentView($row);
    }

    public function enrolments(string $childId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_enrolments')
            ->where('child_id = :child')
            ->setParameter('child', $childId)
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::enrolmentView(...), $rows));
    }

    public function assignments(string $childId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_class_assignments')
            ->where('child_id = :child')
            ->setParameter('child', $childId)
            ->orderBy('valid_from', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    public function enrolmentAt(string $programmeId, string $childId, CalendarDate $on): ?EnrolmentView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM children_enrolments WHERE child_id = :child AND programme_id = :programme AND ended_at IS NULL AND valid_from <= :on AND valid_until > :on',
            ['child' => $childId, 'programme' => $programmeId, 'on' => $on->iso()],
        );

        return false === $row ? null : self::enrolmentView($row);
    }

    public function assignmentAt(string $classId, string $childId, CalendarDate $on): ?AssignmentView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM children_class_assignments WHERE child_id = :child AND class_id = :class AND ended_at IS NULL AND valid_from <= :on AND valid_until > :on',
            ['child' => $childId, 'class' => $classId, 'on' => $on->iso()],
        );

        return false === $row ? null : self::assignmentView($row);
    }

    public function expectedChildren(string $classId, CalendarDate $on, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.* FROM children_class_assignments a JOIN children_enrolments e ON e.id = a.enrolment_id
             WHERE a.class_id = :class AND a.ended_at IS NULL AND a.valid_from <= :on AND a.valid_until > :on
               AND e.ended_at IS NULL AND e.valid_from <= :on AND e.valid_until > :on
             ORDER BY a.child_id LIMIT :limit OFFSET :offset',
            ['class' => $classId, 'on' => $on->iso(), 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(self::assignmentView(...), $rows);
    }

    /** @param array<string, mixed> $row */
    public static function enrolmentView(array $row): EnrolmentView
    {
        return new EnrolmentView(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['child_id'],
            CalendarDate::parse(substr((string) $row['valid_from'], 0, 10)),
            CalendarDate::parse(substr((string) $row['valid_until'], 0, 10)),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private static function assignmentView(array $row): AssignmentView
    {
        return new AssignmentView(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['class_id'],
            (string) $row['child_id'],
            (string) $row['enrolment_id'],
            CalendarDate::parse(substr((string) $row['valid_from'], 0, 10)),
            CalendarDate::parse(substr((string) $row['valid_until'], 0, 10)),
            null === $row['ended_at'] ? null : Timestamps::fromDatabase((string) $row['ended_at']),
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function programmeView(array $row): ProgrammeView
    {
        return new ProgrammeView(
            (string) $row['id'],
            (string) $row['name'],
            null === $row['current_policy_id'] ? null : (string) $row['current_policy_id'],
            (int) $row['min_staff'],
            (int) $row['children_per_staff'],
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private static function classView(array $row): ClassView
    {
        return new ClassView((string) $row['id'], (string) $row['programme_id'], (string) $row['name'], (int) $row['min_age_months'], (int) $row['max_age_months']);
    }
}
