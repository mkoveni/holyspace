<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\InviteVolunteer;

use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerCommand;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerHandler;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesCommand;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesHandler;
use App\Volunteering\Domain\Model\VolunteerInvitation;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class InviteVolunteerHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private ClockInterface $clock,
        private SettleVolunteerResponsesHandler $settleVolunteerResponsesHandler,
        private AssignVolunteerHandler $assignVolunteerHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(InviteVolunteerCommand $message): object
    {
        $shiftId = $message->shiftId;
        $personId = $message->personId;
        $expiresAt = $message->expiresAt;
        $this->manager();
        ($this->settleVolunteerResponsesHandler)(new SettleVolunteerResponsesCommand());

        return $this->transactions->run(function () use ($shiftId, $personId, $expiresAt) {
            $shift = $this->required('shifts', $shiftId);
            $expiry = Input::instant($expiresAt);
            if ($expiry <= $this->clock->now() || $expiry > Input::instant($shift->startsAt)) {
                throw new InvalidArgumentException('Invitation expiry must be in the future and no later than shift start.');
            }
            $assignment = ($this->assignVolunteerHandler)(new AssignVolunteerCommand(['shiftId' => $shiftId, 'personId' => $personId]));
            $this->records->save($assignment->changeStatus('invited'));
            $assignment = $this->required('assignments', $assignment->id);
            $invitation = new VolunteerInvitation($assignment->id, $assignment->version, $shift->version, $personId, $expiry->format(DATE_ATOM), $shift->startsAt, $shift->endsAt);
            $this->records->save($invitation);

            return $this->required('invitations', $invitation->id);
        });
    }

    private function manager(): void
    {
        if (!$this->authorization->isGranted('volunteering.manage')) {
            throw new AccessDenied('Volunteer management permission is required.');
        }
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
