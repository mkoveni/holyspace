<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\EditForm;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Domain\Model\ChurchForm;
use App\Forms\Domain\Repository\FormsRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EditFormHandler
{
    public function __construct(
        private FormsRepository $records,
        private WorkflowTransaction $transactions,
        private OccurrenceDirectory $occurrences,
        private GetFormHandler $getFormHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(EditFormCommand $message): ChurchForm
    {
        $id = $message->id;
        $data = $message->data;
        $version = $message->version;

        return $this->transactions->run(function () use ($id, $data, $version) {
            $form = ($this->getFormHandler)(new GetFormQuery($id));
            $this->version($form->version, $version);
            $this->target($data['eventId'], $data['occurrenceId']);
            $this->records->save($form->edit(...$data));

            return ($this->getFormHandler)(new GetFormQuery($id));
        });
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This record changed. Reload and review before retrying.');
        }
    }

    private function target(?string $eventId, ?string $occurrenceId): void
    {
        if ((null === $eventId) !== (null === $occurrenceId)) {
            throw new InvalidArgumentException('Choose both an event and occurrence.');
        }
        if (null !== $eventId) {
            $occurrence = $this->occurrences->get($occurrenceId);
            if (!$occurrence || $occurrence['eventId'] !== $eventId || 'cancelled' === $occurrence['status']) {
                throw new InvalidArgumentException('Choose a scheduled occurrence for this event.');
            }
        }
    }
}
