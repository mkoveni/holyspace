<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\Gender;
use App\People\Domain\ValueObject\LifeStatus;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Domain\CalendarDate;

final readonly class PersonDataRequest
{
    public function __construct(
        public ?string $titleCode = null,
        public string $firstName = '',
        public string $lastName = '',
        public ?string $dateOfBirth = null,
        public ?Gender $gender = null,
        public ?LifeStatus $lifeStatus = null,
        public ?string $dateOfDeath = null,
    ) {
    }

    public function details(): PersonDetails
    {
        return new PersonDetails(
            $this->titleCode,
            $this->firstName,
            $this->lastName,
            null === $this->dateOfBirth ? null : CalendarDate::parse($this->dateOfBirth),
            $this->gender,
            $this->lifeStatus,
            null === $this->dateOfDeath ? null : CalendarDate::parse($this->dateOfDeath),
        );
    }
}
