<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SaveVolunteerProfile;

use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use App\Volunteering\Domain\Model\VolunteerProfile;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveVolunteerProfileHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private SettleVolunteerInvitationsHandler $settleVolunteerInvitationsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveVolunteerProfileCommand $message): object
    {
        $data = $message->data;
        $id = $message->id;

        return $this->mutate(function () use ($data, $id) {
            if (!$this->people->get($data['personId'])) {
                throw new InvalidArgumentException('Person not found.');
            }
            foreach ($data['approvedRoleIds'] as $roleId) {
                $this->required('roles', $roleId);
            }
            foreach ($this->records->all('profiles') as $profile) {
                if ($profile->personId === $data['personId'] && $profile->id !== $id) {
                    throw new DomainException('This person already has a volunteer profile.');
                }
            }
            $old = null === $id ? null : $this->required('profiles', $id);
            if ($old && $old->personId !== $data['personId']) {
                throw new DomainException('A volunteer profile cannot be moved to another person.');
            }
            $record = new VolunteerProfile(...$data + ($old ? ['id' => $old->id, 'createdAt' => $old->createdAt, 'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM), 'version' => $old->version] : []));
            $this->records->save($record);

            return $this->required('profiles', $record->id);
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
