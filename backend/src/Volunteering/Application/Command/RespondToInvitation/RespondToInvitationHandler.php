<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondToInvitation;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\Command\ChangeAssignmentStatus\ChangeAssignmentStatusCommand;
use App\Volunteering\Application\Command\ChangeAssignmentStatus\ChangeAssignmentStatusHandler;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesCommand;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesHandler;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RespondToInvitationHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private WorkflowTransaction $transactions,
        private AuthenticatedAccount $tokens,
        private AccessControl $authorization,
        private ClockInterface $clock,
        private SettleVolunteerResponsesHandler $settleVolunteerResponsesHandler,
        private ChangeAssignmentStatusHandler $changeAssignmentStatusHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RespondToInvitationCommand $message): object
    {
        $id = $message->id;
        $decision = $message->decision;
        $expectedVersion = $message->expectedVersion;
        $personId = $this->person();
        ($this->settleVolunteerResponsesHandler)(new SettleVolunteerResponsesCommand());

        return $this->transactions->run(function () use ($id, $decision, $expectedVersion, $personId) {
            $invitation = $this->required('invitations', $id);
            if ($invitation->personId !== $personId) {
                throw new AccessDenied('This invitation belongs to another volunteer.');
            }
            if ($invitation->version !== $expectedVersion || 'pending' !== $invitation->status || Input::instant($invitation->expiresAt) <= $this->clock->now()) {
                throw new DomainException('Invitation changed or expired. Reload your schedule.');
            }
            if (!in_array($decision, ['accepted', 'declined'], true)) {
                throw new InvalidArgumentException('Choose accepted or declined.');
            }
            $assignment = $this->required('assignments', $invitation->assignmentId);
            $shift = $this->required('shifts', $assignment->shiftId);
            if ('invited' !== $assignment->status || $assignment->version !== $invitation->assignmentVersion || $shift->version !== $invitation->shiftVersion) {
                throw new DomainException('The shift changed. Ask the coordinator for a new invitation.');
            }
            ($this->changeAssignmentStatusHandler)(new ChangeAssignmentStatusCommand($assignment->id, 'accepted' === $decision ? 'assigned' : 'declined'));
            $this->records->save($invitation->respond($decision, $this->clock->now()->format(DATE_ATOM)));

            return $this->required('invitations', $id);
        });
    }

    private function person(): string
    {
        $user = $this->tokens->user();
        if (!$this->authorization->isGranted('volunteering.serve') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active volunteer account linked to your person record is required.');
        }

        return $user->personId();
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
