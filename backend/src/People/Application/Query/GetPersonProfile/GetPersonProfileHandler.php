<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonProfile;

use App\People\Application\DTO\CommunicationOptionView;
use App\People\Application\DTO\EducationView;
use App\People\Application\DTO\EmploymentView;
use App\People\Application\DTO\PersonAddressView;
use App\People\Application\DTO\PersonProfileView;
use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class GetPersonProfileHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    public function __invoke(GetPersonProfileQuery $q): PersonProfileView
    {
        $profile = $this->read->profile($q->personId);

        return new PersonProfileView(array_map(PersonAddressView::fromRow(...), $profile['addresses']), array_map(CommunicationOptionView::fromRow(...), $profile['communications']), array_map(EducationView::fromRow(...), $profile['education']), array_map(EmploymentView::fromRow(...), $profile['employment']));
    }
}
