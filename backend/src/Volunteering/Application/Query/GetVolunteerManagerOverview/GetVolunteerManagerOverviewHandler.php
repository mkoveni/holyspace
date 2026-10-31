<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteerManagerOverview;

use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Volunteering\Application\Query\GetVolunteeringOverview\GetVolunteeringOverviewHandler;
use App\Volunteering\Application\Query\GetVolunteeringOverview\GetVolunteeringOverviewQuery;
use App\Volunteering\Application\ReadModel\VolunteeringReadStore;

final readonly class GetVolunteerManagerOverviewHandler
{
    public function __construct(
        private VolunteeringReadStore $records,
        private PersonDirectory $people,
        private AccessControl $authorization,
        private GetVolunteeringOverviewHandler $getVolunteeringOverviewHandler,
    ) {
    }

    public function __invoke(GetVolunteerManagerOverviewQuery $message): array
    {
        $this->manager();
        $result = ($this->getVolunteeringOverviewHandler)(new GetVolunteeringOverviewQuery());
        $invitations = $this->records->all('invitations');
        $people = $this->people->getMany(array_column([...$result['assignments'], ...$invitations], 'personId'));
        $label = static fn ($record) => get_object_vars($record) + ['personName' => $people[$record->personId]['name'] ?? 'Person unavailable'];
        $result['assignments'] = array_map($label, $result['assignments']);

        return $result + ['invitations' => array_map($label, $invitations), 'reminders' => $this->records->all('reminders')];
    }

    private function manager(): void
    {
        if (!$this->authorization->isGranted('volunteering.manage')) {
            throw new AccessDenied('Volunteer management permission is required.');
        }
    }
}
