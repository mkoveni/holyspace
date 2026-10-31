<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\Port\MemberContactWriter;
use App\People\Application\ReadModel\MemberPersonalRecords;
use App\People\Domain\Repository\FamilyRepository;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\EmailAddress;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\PhoneNumber;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MemberPersonalRecords::class)]
#[AsAlias(MemberContactWriter::class)]
final readonly class DbalMemberPersonalRecords implements MemberContactWriter
{
    public function __construct(
        private PersonRepository $people,
        private FamilyRepository $families,
        private WorkflowTransaction $transactions,
    ) {
    }

    public function profile(string $personId): array
    {
        $person = $this->people->findById(PersonId::fromString($personId)) ?? throw new OutOfBoundsException('Linked person is unavailable.');
        $data = ['id' => $personId, 'firstName' => $person->name()->firstName(), 'lastName' => $person->name()->lastName(), 'dateOfBirth' => $person->dateOfBirth()?->format('Y-m-d'), 'email' => $person->email()?->value(), 'phone' => $person->phone()?->value()];

        return $data + ['revision' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR).$person->updatedAt()->format('Y-m-d\TH:i:s.uP'))];
    }

    public function families(string $personId): array
    {
        $result = [];
        foreach ($this->families->findForPerson(PersonId::fromString($personId)) as $family) {
            $ownRole = null;
            foreach ($family->members() as $member) {
                if ($member->personId()->toString() === $personId) {
                    $ownRole = $member->role()->value;
                }
            }
            if (null !== $ownRole) {
                $result[] = ['name' => $family->name(), 'yourRole' => $ownRole, 'memberCount' => count($family->members())];
            }
        }

        return $result;
    }

    public function updateContact(string $personId, ?string $email, ?string $phone, string $expectedRevision): array
    {
        return $this->transactions->run(function () use ($personId, $email, $phone, $expectedRevision) {
            if (!hash_equals($this->profile($personId)['revision'], $expectedRevision)) {
                throw new DomainException('Your profile changed. Reload before saving.');
            }
            $person = $this->people->findById(PersonId::fromString($personId)) ?? throw new OutOfBoundsException();
            $person->update($person->name(), $person->dateOfBirth(), $person->gender(), null === $email || '' === trim($email) ? null : EmailAddress::fromString($email), null === $phone || '' === trim($phone) ? null : PhoneNumber::fromString($phone));
            $this->people->save($person);

            return $this->profile($personId);
        });
    }
}
