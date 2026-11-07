<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Infrastructure\Persistence;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessReader;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessCollector;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessConsent;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessEnrolment;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessFacts;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessGuardian;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessPlacement;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Reads the current evidence for a batch of children. Selects only the columns readiness needs, never evidence text. */
final readonly class DbalReadinessStore implements ReadinessReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function load(string $programmeId, ?string $policyId, array $childIds, CalendarDate $on, DateTimeImmutable $at): ReadinessFacts
    {
        if ([] === $childIds) {
            return new ReadinessFacts([], [], [], [], []);
        }
        $day = $on->iso();
        $instant = Timestamps::toDatabase($at);

        $enrolments = $this->rows(
            'SELECT id, programme_id, child_id, valid_from, valid_until, ended_at, version, created_at FROM children_enrolments WHERE programme_id = :programme AND child_id IN (:children) AND ended_at IS NULL AND valid_from <= :day AND valid_until > :day',
            ['programme' => $programmeId, 'children' => $childIds, 'day' => $day],
        );
        $placements = $this->rows(
            'SELECT child_id, enrolment_id, class_id FROM children_class_assignments WHERE programme_id = :programme AND child_id IN (:children) AND ended_at IS NULL AND valid_from <= :day AND valid_until > :day',
            ['programme' => $programmeId, 'children' => $childIds, 'day' => $day],
        );
        $guardians = $this->rows(
            'SELECT id, child_id FROM children_guardian_authorities WHERE child_id IN (:children) AND revoked_at IS NULL AND valid_from <= :at AND valid_until > :at',
            ['children' => $childIds, 'at' => $instant],
        );
        $consents = null === $policyId ? [] : $this->rows(
            'SELECT child_id, authority_id FROM children_consents WHERE programme_id = :programme AND policy_id = :policy AND child_id IN (:children) AND revoked_at IS NULL AND valid_from <= :at AND valid_until > :at',
            ['programme' => $programmeId, 'policy' => $policyId, 'children' => $childIds, 'at' => $instant],
        );
        $collectors = $this->rows(
            'SELECT child_id, authority_id, collector_id FROM children_pickup_authorizations WHERE programme_id = :programme AND child_id IN (:children) AND revoked_at IS NULL AND valid_from <= :at AND valid_until > :at',
            ['programme' => $programmeId, 'children' => $childIds, 'at' => $instant],
        );

        return new ReadinessFacts(
            array_map(static fn (array $r): ReadinessEnrolment => new ReadinessEnrolment(
                (string) $r['id'],
                (string) $r['programme_id'],
                (string) $r['child_id'],
                CalendarDate::parse(substr((string) $r['valid_from'], 0, 10)),
                CalendarDate::parse(substr((string) $r['valid_until'], 0, 10)),
                null === $r['ended_at'] ? null : Timestamps::fromDatabase((string) $r['ended_at']),
                (int) $r['version'],
                Timestamps::fromDatabase((string) $r['created_at']),
            ), $enrolments),
            array_map(static fn (array $r): ReadinessPlacement => new ReadinessPlacement((string) $r['child_id'], (string) $r['enrolment_id'], (string) $r['class_id']), $placements),
            array_map(static fn (array $r): ReadinessGuardian => new ReadinessGuardian((string) $r['id'], (string) $r['child_id']), $guardians),
            array_map(static fn (array $r): ReadinessConsent => new ReadinessConsent((string) $r['child_id'], (string) $r['authority_id']), $consents),
            array_map(static fn (array $r): ReadinessCollector => new ReadinessCollector((string) $r['child_id'], (string) $r['authority_id'], (string) $r['collector_id']), $collectors),
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $parameters): array
    {
        $types = isset($parameters['children']) ? ['children' => ArrayParameterType::STRING] : [];

        return $this->connection->fetchAllAssociative($sql, $parameters, $types);
    }
}
