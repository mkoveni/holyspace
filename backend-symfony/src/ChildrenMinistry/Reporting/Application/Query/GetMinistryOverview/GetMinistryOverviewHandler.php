<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\GetMinistryOverview;

use App\ChildrenMinistry\Incidents\Application\Contract\ActiveIncidents;
use App\ChildrenMinistry\Reporting\Application\Contract\MinistryOverviewReader;
use App\ChildrenMinistry\Reporting\Application\MinistryOverview;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingFollowUps;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningAttention;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Totals and the top five items of each area, gated section by section. It reads no incident description, care payload or
 * guardian evidence, performs no writes, and an oversized cohort becomes a notice rather than a failure.
 */
final readonly class GetMinistryOverviewHandler
{
    private const int TOP = 5;

    public function __construct(
        private PermissionChecker $authorization,
        private MinistryOverviewReader $overview,
        private TeamPlanningAttention $teams,
        private SafeguardingFollowUps $safeguarding,
        private ActiveIncidents $incidents,
        private DeliveryReader $deliveries,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(GetMinistryOverviewQuery $query): MinistryOverview
    {
        $this->authorization->requirePermission(
            $this->authorization->hasPermission('children.read') ? 'children.read'
            : ($this->authorization->hasPermission('children.safeguarding') ? 'children.safeguarding' : 'children.incidents.manage'),
        );
        $now = $this->clock->now();
        $today = $this->calendar->today();
        $zone = new DateTimeZone($this->calendar->timeZone());

        $upcoming = $this->authorization->hasPermission('children.read')
            ? $this->overview->upcoming($now, new DateTimeImmutable($today->plusDays(7)->iso().' 00:00:00', $zone))
            : null;

        $team = null;
        $teamNotice = '';
        if ($this->authorization->hasPermission('children.manage')) {
            try {
                $team = $this->teams->attention($today, $today->plusDays(6), self::TOP);
            } catch (ChildrenException) {
                $teamNotice = 'Narrow the planning dates or programme in Team planning to assess this large schedule.';
            }
        }

        $followUp = null;
        $followUpNotice = '';
        if ($this->authorization->hasPermission('children.safeguarding')) {
            try {
                $followUp = $this->safeguarding->list(null, '', 'ALL', 'ALL', 30, self::TOP, 0, $this->authorization->hasPermission('children.support'));
            } catch (ChildrenException) {
                $followUpNotice = 'Choose a programme in Safeguarding follow-up to review this large cohort.';
            }
        }

        return new MinistryOverview(
            $now,
            $this->calendar->timeZone(),
            $upcoming,
            $team,
            $teamNotice,
            $followUp,
            $followUpNotice,
            $this->authorization->hasPermission('children.incidents.manage') ? $this->incidents->top($today, self::TOP) : null,
            $this->authorization->hasPermission('children.safeguarding') ? $this->deliveries->listForSource('children-ministry', 'ALL', self::TOP, 0) : null,
        );
    }
}
