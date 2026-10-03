<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence\DBAL;

use App\People\Domain\Enum\AddressType;
use App\People\Domain\Enum\CommunicationChannel;
use App\People\Domain\Model\Address;
use App\People\Domain\Model\CommunicationOption;
use App\People\Domain\Model\EducationHistory;
use App\People\Domain\Model\Employment;
use App\People\Domain\Repository\PersonProfileRepository;
use App\People\Domain\ValueObject\AddressId;
use App\People\Domain\ValueObject\CommunicationOptionId;
use App\People\Domain\ValueObject\EducationHistoryId;
use App\People\Domain\ValueObject\EmploymentId;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\FamilyId;
use Doctrine\DBAL\Connection;

final readonly class DbalPersonProfileRepository implements PersonProfileRepository
{
    public function __construct(private Connection $db) {}
    public function addAddress(PersonId $personId, Address $a): void
    {
        $this->db->insert('people_person_addresses', ['id' => $a->id()->toString(), 'person_id' => $personId->toString(), 'type' => $a->type()->value, 'line1' => $a->line1(), 'line2' => $a->line2(), 'city' => $a->city(), 'province' => $a->province(), 'postal_code' => $a->postalCode(), 'country' => $a->country(), 'created_at' => $a->createdAt()->format('Y-m-d H:i:s')]);
    }
    public function addFamilyAddress(FamilyId $familyId, Address $a): void
    {
        $this->db->insert('people_family_addresses', ['id' => $a->id()->toString(), 'family_id' => $familyId->toString(), 'type' => $a->type()->value, 'line1' => $a->line1(), 'line2' => $a->line2(), 'city' => $a->city(), 'province' => $a->province(), 'postal_code' => $a->postalCode(), 'country' => $a->country(), 'created_at' => $a->createdAt()->format('Y-m-d H:i:s')]);
    }
    public function familyAddresses(FamilyId $id): array
    {
        $rows = $this->db->createQueryBuilder()->select('a.*')->from('people_family_addresses', 'a')->where('a.family_id = :id')->setParameter('id', $id->toString())->orderBy('a.created_at', 'ASC')->fetchAllAssociative();
        return array_map(fn($r) => Address::reconstitute(AddressId::fromString($r['id']), AddressType::from($r['type']), $r['line1'], $r['line2'], $r['city'], $r['province'], $r['postal_code'], $r['country'], new \DateTimeImmutable($r['created_at'])), $rows);
    }
    public function addresses(PersonId $id): array
    {
        $rows = $this->db->createQueryBuilder()->select('a.*')->from('people_person_addresses', 'a')->where('a.person_id = :id')->setParameter('id', $id->toString())->orderBy('a.created_at', 'ASC')->fetchAllAssociative();
        return array_map(fn($r) => Address::reconstitute(AddressId::fromString($r['id']), AddressType::from($r['type']), $r['line1'], $r['line2'], $r['city'], $r['province'], $r['postal_code'], $r['country'], new \DateTimeImmutable($r['created_at'])), $rows);
    }
    public function addEducation(PersonId $id, EducationHistory $e): void
    {
        $this->db->insert('people_education_history', ['id' => $e->id()->toString(), 'person_id' => $id->toString(), 'institution' => $e->institution(), 'qualification' => $e->qualification(), 'start_date' => $e->startDate()?->format('Y-m-d'), 'end_date' => $e->endDate()?->format('Y-m-d'), 'field_of_study' => $e->fieldOfStudy(), 'notes' => $e->notes(), 'created_at' => $e->createdAt()->format('Y-m-d H:i:s')]);
    }
    public function education(PersonId $id): array
    {
        $rows = $this->db->createQueryBuilder()->select('e.*')->from('people_education_history', 'e')->where('e.person_id = :id')->setParameter('id', $id->toString())->orderBy('e.start_date', 'DESC')->addOrderBy('e.created_at', 'DESC')->fetchAllAssociative();
        return array_map(fn($r) => EducationHistory::reconstitute(EducationHistoryId::fromString($r['id']), $r['institution'], $r['qualification'], $r['start_date'] ? new \DateTimeImmutable($r['start_date']) : null, $r['end_date'] ? new \DateTimeImmutable($r['end_date']) : null, $r['field_of_study'], $r['notes'], new \DateTimeImmutable($r['created_at'])), $rows);
    }
    public function addEmployment(PersonId $id, Employment $e): void
    {
        $this->db->insert('people_employment_history', ['id' => $e->id()->toString(), 'person_id' => $id->toString(), 'employer' => $e->employer(), 'job_title' => $e->jobTitle(), 'start_date' => $e->startDate()?->format('Y-m-d'), 'end_date' => $e->endDate()?->format('Y-m-d'), 'industry' => $e->industry(), 'work_email' => $e->workEmail(), 'work_phone' => $e->workPhone(), 'notes' => $e->notes(), 'created_at' => $e->createdAt()->format('Y-m-d H:i:s')]);
    }
    public function employment(PersonId $id): array
    {
        $rows = $this->db->createQueryBuilder()->select('e.*')->from('people_employment_history', 'e')->where('e.person_id = :id')->setParameter('id', $id->toString())->orderBy('e.start_date', 'DESC')->addOrderBy('e.created_at', 'DESC')->fetchAllAssociative();
        return array_map(fn($r) => Employment::reconstitute(EmploymentId::fromString($r['id']), $r['employer'], $r['job_title'], $r['start_date'] ? new \DateTimeImmutable($r['start_date']) : null, $r['end_date'] ? new \DateTimeImmutable($r['end_date']) : null, $r['industry'], $r['work_email'], $r['work_phone'], $r['notes'], new \DateTimeImmutable($r['created_at'])), $rows);
    }
}
