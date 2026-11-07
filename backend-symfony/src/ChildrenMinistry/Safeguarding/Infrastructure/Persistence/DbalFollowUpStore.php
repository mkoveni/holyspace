<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Infrastructure\Persistence;

use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpReader;
use App\ChildrenMinistry\Safeguarding\Application\FollowUpCare;
use App\ChildrenMinistry\Safeguarding\Application\FollowUpChild;
use App\ChildrenMinistry\Safeguarding\Application\FollowUpFacts;
use App\ChildrenMinistry\Safeguarding\Application\FollowUpPickup;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\ConsistentRead;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Reads dates only. The support table is touched for its review date, never for allergies, instructions or notes. */
final readonly class DbalFollowUpStore implements FollowUpReader
{
    private const int LIMIT = 1000;

    public function __construct(private Connection $connection)
    {
    }

    public function load(?string $programmeId, ?array $matchingChildIds, CalendarDate $on, DateTimeImmutable $at, bool $includeCare): FollowUpFacts
    {
        return ConsistentRead::run($this->connection, fn (): FollowUpFacts => $this->read($programmeId, $matchingChildIds, $on, $at, $includeCare));
    }

    /** @param list<string>|null $matchingChildIds */
    private function read(?string $programmeId, ?array $matchingChildIds, CalendarDate $on, DateTimeImmutable $at, bool $includeCare): FollowUpFacts
    {
        if (null !== $matchingChildIds && [] === $matchingChildIds) {
            return new FollowUpFacts([], [], []);
        }
        $where = 'e.ended_at IS NULL AND e.valid_from <= :on AND e.valid_until > :on';
        $parameters = ['on' => $on->iso(), 'at' => Timestamps::toDatabase($at), 'limit' => self::LIMIT + 1];
        $types = [];
        if (null !== $programmeId) {
            $where .= ' AND e.programme_id = :programme';
            $parameters['programme'] = $programmeId;
        }
        if (null !== $matchingChildIds) {
            $where .= ' AND e.child_id IN (:matching)';
            $parameters['matching'] = $matchingChildIds;
            $types['matching'] = ArrayParameterType::STRING;
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT e.child_id, e.programme_id, p.name, p.current_policy_id,
                    (SELECT max(a.valid_until) FROM children_guardian_authorities a WHERE a.revoked_at IS NULL AND a.valid_from <= :at AND a.child_id = e.child_id) AS guardian_until,
                    (SELECT max(least(c.valid_until, a.valid_until)) FROM children_consents c JOIN children_guardian_authorities a ON a.id = c.authority_id
                      WHERE a.revoked_at IS NULL AND a.valid_from <= :at AND c.revoked_at IS NULL AND c.valid_from <= :at AND c.child_id = e.child_id
                        AND c.programme_id = e.programme_id AND c.policy_id = p.current_policy_id) AS consent_until
             FROM children_enrolments e JOIN children_programmes p ON p.id = e.programme_id
             WHERE $where ORDER BY e.child_id, e.programme_id LIMIT :limit",
            $parameters,
            $types,
        );
        $children = array_map(static fn (array $row): FollowUpChild => new FollowUpChild(
            (string) $row['child_id'],
            (string) $row['programme_id'],
            (string) $row['name'],
            null === $row['current_policy_id'] ? null : (string) $row['current_policy_id'],
            null === $row['guardian_until'] ? null : Timestamps::fromDatabase((string) $row['guardian_until']),
            null === $row['consent_until'] ? null : Timestamps::fromDatabase((string) $row['consent_until']),
        ), $rows);
        if ([] === $children || \count($children) > self::LIMIT) {
            return new FollowUpFacts($children, [], []);
        }

        $childIds = array_values(array_unique(array_map(static fn (FollowUpChild $c): string => $c->childId, $children)));
        $programmes = array_values(array_unique(array_map(static fn (FollowUpChild $c): string => $c->programmeId, $children)));
        $pickupRows = $this->connection->fetchAllAssociative(
            'SELECT pa.child_id, pa.programme_id, pa.collector_id, max(least(pa.valid_until, a.valid_until)) AS until
             FROM children_pickup_authorizations pa JOIN children_guardian_authorities a ON a.id = pa.authority_id
             WHERE a.revoked_at IS NULL AND a.valid_from <= :at AND pa.revoked_at IS NULL AND pa.valid_from <= :at AND pa.child_id IN (:children) AND pa.programme_id IN (:programmes)
             GROUP BY pa.child_id, pa.programme_id, pa.collector_id',
            ['at' => Timestamps::toDatabase($at), 'children' => $childIds, 'programmes' => $programmes],
            ['children' => ArrayParameterType::STRING, 'programmes' => ArrayParameterType::STRING],
        );
        $pickups = array_map(static fn (array $row): FollowUpPickup => new FollowUpPickup((string) $row['child_id'], (string) $row['programme_id'], (string) $row['collector_id'], Timestamps::fromDatabase((string) $row['until'])), $pickupRows);

        $care = [];
        if ($includeCare) {
            $careRows = $this->connection->fetchAllAssociative('SELECT child_id, review_on FROM children_support_records WHERE child_id IN (:children) AND retain_until >= :on', ['children' => $childIds, 'on' => $on->iso()], ['children' => ArrayParameterType::STRING]);
            $care = array_map(static fn (array $row): FollowUpCare => new FollowUpCare((string) $row['child_id'], CalendarDate::parse((string) $row['review_on'])), $careRows);
        }

        return new FollowUpFacts($children, $pickups, $care);
    }
}
