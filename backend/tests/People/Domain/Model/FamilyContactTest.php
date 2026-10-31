<?php

declare(strict_types=1);

namespace App\Tests\People\Domain\Model;

use App\People\Domain\Enum\FamilyRole;
use App\People\Domain\Model\Family;
use App\People\Domain\ValueObject\FamilyId;
use App\People\Domain\ValueObject\PersonId;
use App\People\Infrastructure\Persistence\DBAL\FamilyMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FamilyContactTest extends TestCase
{
    public function testContactPersistsThroughMappingAndIsClearedWhenMemberLeaves(): void
    {
        $family = Family::create(FamilyId::generate(), 'Doe');
        $person = PersonId::generate();
        $family->addMember($person, FamilyRole::HEAD);
        $family->setPrimaryContact($person);
        $mapper = new FamilyMapper();
        $restored = $mapper->toDomain($mapper->toRow($family), [['person_id' => $person->toString(), 'role' => 'head', 'created_at' => '2026-10-03']]);
        self::assertSame($person->toString(), $restored->primaryContact()->toString());
        $restored->removeMember($person);
        self::assertNull($restored->primaryContact());
    }

    public function testNonMemberCannotBePrimaryContact(): void
    {
        $family = Family::create(FamilyId::generate(), 'Doe');
        $this->expectException(InvalidArgumentException::class);
        $family->setPrimaryContact(PersonId::generate());
    }
}
