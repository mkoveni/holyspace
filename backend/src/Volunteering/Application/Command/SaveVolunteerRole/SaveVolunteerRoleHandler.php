<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SaveVolunteerRole;

use App\Shared\Application\WorkflowTransaction;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use App\Volunteering\Domain\Model\VolunteerRole;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveVolunteerRoleHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private WorkflowTransaction $transactions,
        private SettleVolunteerInvitationsHandler $settleVolunteerInvitationsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveVolunteerRoleCommand $message): object
    {
        $data = $message->data;

        return $this->mutate(function () use ($data) {
            $record = new VolunteerRole(...$data);
            $this->records->save($record);

            return $this->required('roles', $record->id);
        });
    }

    private function mutate(callable $operation): mixed
    {
        ($this->settleVolunteerInvitationsHandler)(new SettleVolunteerInvitationsCommand());

        return $this->transactions->run($operation);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
