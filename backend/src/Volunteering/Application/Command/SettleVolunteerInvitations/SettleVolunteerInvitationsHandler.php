<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SettleVolunteerInvitations;

use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SettleVolunteerInvitationsHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SettleVolunteerInvitationsCommand $message): void
    {
        $this->outbox->drainAll();
        $this->transactions->run(function () {
            foreach ($this->records->all('invitations') as $invitation) {
                if ('pending' !== $invitation->status) {
                    continue;
                }
                $assignment = $this->required('assignments', $invitation->assignmentId);
                $shift = $this->required('shifts', $assignment->shiftId);
                if ('invited' !== $assignment->status || $assignment->version !== $invitation->assignmentVersion || $shift->version !== $invitation->shiftVersion) {
                    $this->records->save($invitation->respond('withdrawn', $this->clock->now()->format(DATE_ATOM)));
                    continue;
                }
                if (Input::instant($invitation->expiresAt) <= $this->clock->now()) {
                    $this->records->save($assignment->changeStatus('cancelled'));
                    $this->records->save($invitation->respond('expired', $this->clock->now()->format(DATE_ATOM)));
                }
            }
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
