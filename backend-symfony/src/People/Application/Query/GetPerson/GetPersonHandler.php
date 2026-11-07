<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPerson;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonReader;
use App\People\Application\PersonView;
use App\People\Domain\PeopleException;
use App\Shared\Application\ChurchCalendar;

final readonly class GetPersonHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonReader $persons,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(GetPersonQuery $query): PersonView
    {
        $this->authorization->requirePermission('people.read');

        return $this->persons->find($query->personId, $query->asOf ?? $this->calendar->today())
            ?? throw PeopleException::missing('Person');
    }
}
