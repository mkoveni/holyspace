<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\ChangeMinistryRole;

use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ChangeMinistryRoleHandler
{
    public function __construct(private MinistriesRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(ChangeMinistryRoleCommand $message): object
    {
        $id = $message->id;
        $role = $message->role;

        return $this->transactions->run(function () use ($id, $role) {
            $record = $this->required('memberships', $id);
            if (!$record->active || !$this->required('ministries', $record->ministryId)->active) {
                throw new DomainException('Membership is inactive.');
            }

            return $this->save('memberships', $record->changeRole($role));
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}
