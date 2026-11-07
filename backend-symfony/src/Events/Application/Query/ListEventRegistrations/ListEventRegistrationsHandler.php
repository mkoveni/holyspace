<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventRegistrations;

use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\PersonRegistration;
use App\Events\Application\Contract\RegistrationPage;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;

final readonly class ListEventRegistrationsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private RegistrationReader $registrations,
        private PersonDirectory $people,
    ) {
    }

    public function handle(ListEventRegistrationsQuery $query): RegistrationPage
    {
        $this->authorization->requirePermission('events.registrations.read');
        EventRules::page($query->limit, $query->offset);
        $this->occurrences->find($query->occurrenceId) ?? throw EventException::missing('Occurrence');
        if (null !== $query->status && !\in_array($query->status, ['CONFIRMED', 'WAITLISTED', 'CANCELLED'], true)) {
            throw EventException::invalid('Choose a registration status.');
        }
        $page = $this->registrations->registrations($query->occurrenceId, $query->status, $query->limit, $query->offset);
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (RegistrationView $r): string => $r->personId, $page)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return new RegistrationPage(
            array_map(static fn (RegistrationView $r): PersonRegistration => new PersonRegistration($r, $names[$r->personId] ?? 'Unavailable person'), $page),
            $this->registrations->registrationCount($query->occurrenceId, $query->status),
            $this->registrations->counts($query->occurrenceId),
        );
    }
}
