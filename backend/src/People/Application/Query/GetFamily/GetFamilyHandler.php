<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetFamily;

use App\People\Application\DTO\FamilyAddressView;
use App\People\Application\DTO\FamilyMemberView;
use App\People\Application\DTO\FamilyView;
use App\People\Application\ReadModel\PeopleReadRepository;
use RuntimeException;

final readonly class GetFamilyHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    public function __invoke(GetFamilyQuery $q): FamilyView
    {
        $f = $this->read->family($q->id);
        if (null === $f) {
            throw new RuntimeException('Family not found.');
        }

        return new FamilyView($f['id'], $f['name'], array_map(FamilyMemberView::fromRow(...), $f['members']), array_map(FamilyAddressView::fromRow(...), $f['addresses']), $f['created_at'], $f['updated_at'], $f['primary_contact_person_id']);
    }
}
