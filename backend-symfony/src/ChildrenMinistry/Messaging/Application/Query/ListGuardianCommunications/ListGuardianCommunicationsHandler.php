<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\ListGuardianCommunications;

use App\ChildrenMinistry\Messaging\Application\CommunicationListView;
use App\ChildrenMinistry\Messaging\Application\CommunicationRow;
use App\ChildrenMinistry\Messaging\Application\CommunicationSummary;
use App\ChildrenMinistry\Messaging\Application\Contract\GuardianCommunicationReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;

/** The worklist: task metadata, names and delivery outcome, never the wording. */
final readonly class ListGuardianCommunicationsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private GuardianCommunicationReader $reader,
        private PersonDirectory $people,
        private DeliveryReader $deliveries,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(ListGuardianCommunicationsQuery $query): CommunicationListView
    {
        $this->authorization->requirePermission('children.safeguarding');
        ChildrenRules::page($query->limit, $query->offset);
        if (!\in_array($query->status, ['ALL', 'DRAFT', 'CONTACTED', 'CANCELLED'], true)) {
            throw ChildrenException::invalid('Choose a valid communication status.');
        }
        $page = $this->reader->list($query->childId, $query->status, $this->calendar->today(), $query->limit, $query->offset);
        $ids = [];
        foreach ($page->communications as $communication) {
            $ids[$communication->childId] = true;
            $ids[$communication->guardianId] = true;
        }
        $names = [];
        foreach ($this->people->getAll(array_keys($ids)) as $person) {
            $names[$person->id] = $person->name;
        }
        $outcomes = $this->deliveries->summaries(array_map(static fn (CommunicationSummary $c): string => $c->id, $page->communications));

        return new CommunicationListView(
            array_map(static fn (CommunicationSummary $c): CommunicationRow => new CommunicationRow($c, $names[$c->childId] ?? 'Unavailable person', $names[$c->guardianId] ?? 'Unavailable person', $outcomes[$c->id] ?? null), $page->communications),
            $page->total,
            $page->overdue,
        );
    }
}
