<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\ReadModel\PeopleReadRepository;
use Doctrine\DBAL\Connection;

final readonly class DbalPeopleReadRepository implements PeopleReadRepository
{
    public function __construct(private Connection $db) {}

    public function person(string $personId): ?array
    {
        $row = $this->db->createQueryBuilder()->select('p.*')->from('people_persons', 'p')->where('p.id = :id')->setParameter('id', $personId)->fetchAssociative();
        return $row === false ? null : $row;
    }

    public function people(): array
    {
        return $this->db->createQueryBuilder()->select('p.*')->from('people_persons', 'p')->orderBy('p.last_name', 'ASC')->addOrderBy('p.first_name', 'ASC')->fetchAllAssociative();
    }

    public function family(string $familyId): ?array
    {
        $family = $this->db->createQueryBuilder()
            ->select('f.*')
            ->from('people_families', 'f')
            ->where('f.id = :id')
            ->setParameter('id', $familyId)
            ->fetchAssociative();

        if ($family === false) return null;

        $family['members'] = $this->peopleInFamily($familyId);
        $family['addresses'] = $this->db->createQueryBuilder()->select('a.*')->from('people_family_addresses', 'a')->where('a.family_id = :id')->setParameter('id', $familyId)->orderBy('a.created_at', 'ASC')->fetchAllAssociative();
        return $family;
    }

    public function families(): array
    {
        return $this->db->createQueryBuilder()
            ->select('f.id', 'f.name', 'f.created_at', 'f.updated_at', 'COUNT(m.person_id) AS member_count')
            ->from('people_families', 'f')->leftJoin('f', 'people_family_memberships', 'm', 'm.family_id = f.id')
            ->groupBy('f.id', 'f.name', 'f.created_at', 'f.updated_at')->orderBy('f.name', 'ASC')->fetchAllAssociative();
    }

    public function peopleInFamily(string $familyId): array
    {
        return $this->db->createQueryBuilder()
            ->select('p.id', 'p.first_name', 'p.last_name', 'p.date_of_birth', 'p.gender', 'p.email', 'p.phone', 'm.role', 'm.created_at AS membership_created_at')
            ->from('people_family_memberships', 'm')
            ->innerJoin('m', 'people_persons', 'p', 'p.id = m.person_id')
            ->where('m.family_id = :familyId')
            ->setParameter('familyId', $familyId)
            ->orderBy('p.last_name', 'ASC')->addOrderBy('p.first_name', 'ASC')->fetchAllAssociative();
    }

    public function familiesForPerson(string $personId): array
    {
        return $this->db->createQueryBuilder()
            ->select('f.id', 'f.name', 'm.role', 'm.created_at AS membership_created_at', 'f.created_at', 'f.updated_at')
            ->from('people_family_memberships', 'm')->innerJoin('m', 'people_families', 'f', 'f.id = m.family_id')
            ->where('m.person_id = :personId')->setParameter('personId', $personId)->orderBy('f.name', 'ASC')->fetchAllAssociative();
    }

    public function relationshipsForPerson(string $personId): array
    {
        return $this->db->createQueryBuilder()
            ->select('r.*', 'rp.first_name AS related_first_name', 'rp.last_name AS related_last_name')
            ->from('people_relationships', 'r')->innerJoin('r', 'people_persons', 'rp', 'rp.id = r.related_person_id')
            ->where('r.person_id = :personId')->setParameter('personId', $personId)->orderBy('r.created_at', 'ASC')->fetchAllAssociative();
    }

    public function profile(string $personId): array
    {
        $id = ['personId' => $personId];
        $addresses = $this->db->createQueryBuilder()->select('a.*')->from('people_person_addresses', 'a')->where('a.person_id = :personId')->setParameters($id)->orderBy('a.created_at', 'ASC')->fetchAllAssociative();
        $communications = $this->db->createQueryBuilder()->select('c.*')->from('people_communication_options', 'c')->where('c.person_id = :personId')->setParameters($id)->orderBy('c.preferred', 'DESC')->addOrderBy('c.created_at', 'ASC')->fetchAllAssociative();
        $education = $this->db->createQueryBuilder()->select('e.*')->from('people_education_history', 'e')->where('e.person_id = :personId')->setParameters($id)->orderBy('e.start_date', 'DESC')->addOrderBy('e.created_at', 'DESC')->fetchAllAssociative();
        $employment = $this->db->createQueryBuilder()->select('e.*')->from('people_employment_history', 'e')->where('e.person_id = :personId')->setParameters($id)->orderBy('e.start_date', 'DESC')->addOrderBy('e.created_at', 'DESC')->fetchAllAssociative();
        return compact('addresses', 'communications', 'education', 'employment');
    }
}
