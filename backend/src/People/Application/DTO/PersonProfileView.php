<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class PersonProfileView
{
    /**
     * @param list<PersonAddressView>       $addresses
     * @param list<CommunicationOptionView> $communicationOptions
     * @param list<EducationView>           $education
     * @param list<EmploymentView>          $employment
     */
    public function __construct(
        public array $addresses,
        public array $communicationOptions,
        public array $education,
        public array $employment,
    ) {
    }
}
