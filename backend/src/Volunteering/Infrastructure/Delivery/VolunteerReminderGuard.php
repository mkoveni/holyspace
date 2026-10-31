<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Delivery;

use App\Communications\Application\Port\DeliveryGuard;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Domain\Model\VolunteerReminder;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use App\Volunteering\Domain\Service\VolunteerEligibility;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('onechurch.delivery_guard')]
final readonly class VolunteerReminderGuard implements DeliveryGuard
{
    public function __construct(
        private VolunteeringRepository $records,
        private OccurrenceDirectory $occurrences,
        private ClockInterface $clock,
    ) {
    }

    public function suppressionReason(string $campaignId): ?string
    {
        foreach ($this->records->all('reminders') as $reminder) {
            if ($reminder->campaignId !== $campaignId) {
                continue;
            }
            $a = $this->records->find('assignments', $reminder->assignmentId);
            $shift = $a ? $this->records->find('shifts', $a->shiftId) : null;
            $occurrence = $shift ? $this->occurrences->get($shift->occurrenceId) : null;
            if (!$a || !$shift || !$occurrence || $a->status !== $reminder->assignmentStatus || !in_array($a->status, ['invited', 'assigned'], true) || 'open' !== $shift->status || $shift->version !== $reminder->shiftVersion || 'cancelled' === $occurrence['status'] || Input::instant($shift->endsAt) <= $this->clock->now() || VolunteerReminder::signature($occurrence) !== $reminder->signature) {
                return 'Volunteer assignment changed or closed; coordinator review required.';
            }
            $role = $this->records->find('roles', $shift->roleId);
            $eligible = false;
            foreach ($this->records->all('profiles') as $profile) {
                if ($profile->personId === $a->personId && $role) {
                    try {
                        VolunteerEligibility::requireEligible($profile, $role, $shift);
                        $eligible = true;
                    } catch (DomainException) {
                    }
                }
            }
            if (!$eligible) {
                return 'Volunteer eligibility changed; coordinator review required.';
            }
            if ('invited' === $a->status) {
                foreach ($this->records->all('invitations') as $invitation) {
                    if ($invitation->assignmentId === $a->id && 'pending' === $invitation->status && $invitation->assignmentVersion === $a->version && Input::instant($invitation->expiresAt) > $this->clock->now()) {
                        return null;
                    }
                }

                return 'Volunteer invitation expired or changed.';
            }

            return null;
        }

        return null;
    }
}
