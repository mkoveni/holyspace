<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class FamilyOverviewView
{
    /**
     * @param list<FamilyMemberView>          $members
     * @param list<FamilyOverviewAddressView> $addresses
     * @param list<FamilyUpcomingEventView>   $upcomingEvents
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $createdAt,
        public string $updatedAt,
        public array $members,
        public array $addresses,
        public array $upcomingEvents,
        public ?FamilyMemberView $primaryContact,
    ) {
    }
}
