<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\DTO\FamilyMemberView;
use App\People\Application\DTO\FamilyOverviewAddressView;
use App\People\Application\DTO\FamilyOverviewView;
use App\People\Application\DTO\FamilyUpcomingEventView;
use App\People\Application\ReadModel\FamilyOverviewRepository;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(FamilyOverviewRepository::class)]
final readonly class DbalFamilyOverviewRepository implements FamilyOverviewRepository
{
    public function __construct(private Connection $db, private ClockInterface $clock)
    {
    }

    public function overview(?string $familyId = null): array
    {
        $query = $this->db->createQueryBuilder()
            ->select('f.*')
            ->from('people_families', 'f')
            ->orderBy('f.name', 'ASC');

        if (null !== $familyId) {
            $query->where('f.id = :id')->setParameter('id', $familyId);
        }

        $rows = $query->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }
        $ids = array_column($rows, 'id');
        $members = $this->db->executeQuery('SELECT m.family_id, m.role, m.created_at AS membership_created_at, p.id, p.first_name, p.last_name, p.date_of_birth, p.gender, p.email, p.phone FROM people_family_memberships m INNER JOIN people_persons p ON p.id = m.person_id WHERE m.family_id IN (?) ORDER BY p.last_name, p.first_name', [$ids], [ArrayParameterType::STRING])->fetchAllAssociative();
        $addresses = $this->db->executeQuery('SELECT * FROM people_family_addresses WHERE family_id IN (?) ORDER BY created_at DESC, id', [$ids], [ArrayParameterType::STRING])->fetchAllAssociative();
        $today = $this->clock->now()->setTime(0, 0);
        $events = $this->db->executeQuery("SELECT DISTINCT m.family_id, m.person_id, e.id, e.type, e.event_date, e.notes, e.status FROM life_events e INNER JOIN life_event_participants ep ON ep.life_event_id = e.id INNER JOIN people_family_memberships m ON m.person_id = ep.person_id WHERE m.family_id IN (?) AND e.status <> 'cancelled' AND ((e.status = 'active' AND e.event_date >= ?) OR (e.type IN ('birth', 'marriage', 'engagement') AND e.event_date < ?)) ORDER BY e.event_date, e.id", [$ids, $today->format('Y-m-d'), $today->format('Y-m-d')], [ArrayParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING])->fetchAllAssociative();
        $membersByFamily = [];
        foreach ($members as $member) {
            $membersByFamily[$member['family_id']][] = FamilyMemberView::fromRow($member);
        }
        $addressesByFamily = [];
        foreach ($addresses as $address) {
            $addressesByFamily[$address['family_id']][] = FamilyOverviewAddressView::fromRow($address);
        }
        $eventsByFamily = [];
        $eventMemberIds = [];
        foreach ($events as $event) {
            $event['original_event_date'] = $event['event_date'];
            $event['is_anniversary'] = $event['event_date'] < $today->format('Y-m-d');
            if ($event['is_anniversary']) {
                $originalDate = new DateTimeImmutable($event['event_date'], $today->getTimezone());
                $year = (int) $today->format('Y');
                $nextDate = self::annualDate($originalDate, $year);
                if ($nextDate < $today) {
                    $nextDate = self::annualDate($originalDate, $year + 1);
                }
                $event['event_date'] = $nextDate->format('Y-m-d');
            }
            $eventsByFamily[$event['family_id']][$event['id']] = $event;
            $eventMemberIds[$event['family_id']][$event['id']][] = $event['person_id'];
        }

        $families = [];
        foreach ($rows as $row) {
            $familyMembers = $membersByFamily[$row['id']] ?? [];
            $primaryContact = null;
            foreach ($familyMembers as $member) {
                if ($member->id === $row['primary_contact_person_id']) {
                    $primaryContact = $member;
                    break;
                }
            }
            $upcomingEvents = [];
            $familyEvents = array_values($eventsByFamily[$row['id']] ?? []);
            usort($familyEvents, static fn (array $a, array $b): int => [$a['event_date'], $a['id']] <=> [$b['event_date'], $b['id']]);
            foreach ($familyEvents as $event) {
                $upcomingEvents[] = new FamilyUpcomingEventView(
                    $event['id'],
                    $event['type'],
                    $event['event_date'],
                    $event['notes'],
                    $event['status'],
                    $eventMemberIds[$row['id']][$event['id']],
                    $event['is_anniversary'],
                    $event['original_event_date'],
                );
            }
            $families[] = new FamilyOverviewView(
                $row['id'],
                $row['name'],
                $row['created_at'],
                $row['updated_at'],
                $familyMembers,
                $addressesByFamily[$row['id']] ?? [],
                $upcomingEvents,
                $primaryContact,
            );
        }

        return $families;
    }

    private static function annualDate(DateTimeImmutable $original, int $year): DateTimeImmutable
    {
        $month = (int) $original->format('n');
        $first = $original->setDate($year, $month, 1);

        // Observe February 29 milestones on February 28 in non-leap years.
        return $first->setDate($year, $month, min((int) $original->format('j'), (int) $first->format('t')));
    }
}
