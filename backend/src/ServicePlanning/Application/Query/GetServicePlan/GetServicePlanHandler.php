<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\GetServicePlan;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\ServicePlanning\Application\ReadModel\ServicePlanningReadStore;
use App\ServicePlanning\Domain\Model\ServicePlan;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Volunteering\Application\ReadModel\VolunteeringReadStore;
use OutOfBoundsException;

final readonly class GetServicePlanHandler
{
    public function __construct(private ServicePlanningReadStore $records, private OccurrenceDirectory $occurrences, private VolunteeringReadStore $volunteers, private AccessControl $authorization, private AuthenticatedAccount $tokens)
    {
    }

    public function __invoke(GetServicePlanQuery $message): array
    {
        $id = $message->id;
        $plan = $this->required($id);
        $occurrence = $this->occurrences->get($plan->occurrenceId);
        if (!$this->authorization->isGranted('service_planning.read')) {
            $user = $this->tokens->user();
            $allowed = false;
            if ('published' === $plan->status && $occurrence && 'cancelled' !== $occurrence['status'] && $this->authorization->isGranted('volunteering.serve') && null !== $user && 'active' === $user->status()->value && $user->personId()) {
                $shifts = array_column($this->volunteers->all('shifts'), null, 'id');
                foreach ($this->volunteers->all('assignments') as $a) {
                    if ($a->personId === $user->personId() && in_array($a->status, ['invited', 'assigned', 'needs_review'], true)) {
                        $shift = $shifts[$a->shiftId] ?? null;
                        if ($shift?->occurrenceId === $plan->occurrenceId) {
                            $allowed = true;
                        }
                    }
                }
            }
            if (!$allowed) {
                throw new AccessDenied('This service plan is outside your serving scope.');
            }
        }

        return ['plan' => $plan, 'occurrence' => $occurrence, 'durationMinutes' => array_sum(array_column($plan->items, 'durationMinutes'))];
    }

    private function required(string $id): ServicePlan
    {
        return $this->records->find('plans', $id) ?? throw new OutOfBoundsException('Plan not found.');
    }
}
