<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

use App\People\Domain\PeopleException;
use App\People\Domain\PeopleRules;
use App\Shared\Domain\CalendarDate;

final readonly class PersonDetails
{
    public ?string $titleCode;
    public string $firstName;
    public string $lastName;
    public Gender $gender;
    public LifeStatus $lifeStatus;

    public function __construct(
        ?string $titleCode,
        string $firstName,
        string $lastName,
        public ?CalendarDate $dateOfBirth,
        ?Gender $gender,
        ?LifeStatus $lifeStatus,
        public ?CalendarDate $dateOfDeath,
    ) {
        $this->titleCode = PeopleRules::titleCode($titleCode);
        $this->firstName = PeopleRules::text($firstName, 'First name', 100);
        $this->lastName = PeopleRules::text($lastName, 'Last name', 100);
        $this->gender = $gender ?? Gender::Unknown;
        $this->lifeStatus = $lifeStatus ?? LifeStatus::Unknown;
        if (null !== $dateOfDeath
            && (LifeStatus::Deceased !== $this->lifeStatus || (null !== $dateOfBirth && $dateOfDeath->isBefore($dateOfBirth)))) {
            throw PeopleException::invalid('Date of death must follow birth and requires deceased life status.');
        }
    }

    public function validateAt(CalendarDate $today): void
    {
        if ((null !== $this->dateOfBirth && $this->dateOfBirth->isAfter($today))
            || (null !== $this->dateOfDeath && $this->dateOfDeath->isAfter($today))) {
            throw PeopleException::invalid('Birth and death dates cannot be in the future.');
        }
    }

    public function withProfile(?string $titleCode, string $firstName, string $lastName, ?Gender $gender): self
    {
        return new self($titleCode, $firstName, $lastName, $this->dateOfBirth, $gender, $this->lifeStatus, $this->dateOfDeath);
    }
}
