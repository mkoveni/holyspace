<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\JoinMinistry;

use App\Ministries\Domain\Model\MinistryMembership;
use App\Ministries\Domain\Repository\MinistriesRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class JoinMinistryHandler
{
    public function __construct(
        private MinistriesRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(JoinMinistryCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            if (!$this->required('ministries', $data['ministryId'])->active) {
                throw new DomainException('Ministry is archived.');
            }
            if (!$this->people->get($data['personId'])) {
                throw new InvalidArgumentException('Person not found.');
            }
            foreach ($this->records->all('memberships') as $membership) {
                if ($membership->active && $membership->ministryId === $data['ministryId'] && $membership->personId === $data['personId']) {
                    throw new DomainException('Person is already an active member.');
                }
            }

            return $this->save('memberships', new MinistryMembership(...$data));
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
