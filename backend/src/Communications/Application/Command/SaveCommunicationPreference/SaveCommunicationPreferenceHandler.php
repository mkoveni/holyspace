<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SaveCommunicationPreference;

use App\Communications\Domain\Model\CommunicationPreference;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveCommunicationPreferenceHandler
{
    public function __construct(
        private CommunicationsRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveCommunicationPreferenceCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            if (!$this->people->get($data['personId'])) {
                throw new InvalidArgumentException('Person not found.');
            }
            $old = null;
            foreach ($this->records->all('preferences') as $preference) {
                if ($preference->personId === $data['personId'] && $preference->channel === $data['channel']) {
                    $old = $preference;
                }
            }
            $record = new CommunicationPreference(...$data + ($old ? ['id' => $old->id, 'createdAt' => $old->createdAt, 'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM), 'version' => $old->version] : []));

            return $this->save('preferences', $record);
        });
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
