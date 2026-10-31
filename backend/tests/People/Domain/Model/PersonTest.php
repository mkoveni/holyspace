<?php

declare(strict_types=1);

namespace App\Tests\People\Domain\Model;

use App\People\Domain\Enum\Gender;
use App\People\Domain\Enum\MembershipStatus;
use App\People\Domain\Model\Person;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\PersonName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PersonTest extends TestCase
{
    public function testRegistrationStartsAsProspectAndCreatesAuditTimestamps(): void
    {
        $person = Person::register(PersonId::generate(), PersonName::fromStrings('John', 'Doe'), null, Gender::MALE, null, null);
        self::assertSame(MembershipStatus::PROSPECT, $person->status());
        self::assertSame('John Doe', $person->name()->fullName());
        self::assertSame($person->createdAt(), $person->updatedAt());
        self::assertCount(1, $person->releaseDomainEvents());
    }

    public function testStatusChangeUpdatesTimestamp(): void
    {
        $updated = new DateTimeImmutable('2026-01-01 10:01:00');
        $person = Person::register(PersonId::generate(), PersonName::fromStrings('Jane', 'Doe'), null, Gender::FEMALE, null, null, new DateTimeImmutable('2026-01-01 10:00:00'));
        $person->changeStatus(MembershipStatus::ACTIVE, $updated);
        self::assertSame(MembershipStatus::ACTIVE, $person->status());
        self::assertSame($updated, $person->updatedAt());
    }
}
