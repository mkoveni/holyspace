<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CreateForm;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Domain\Model\ChurchForm;
use App\Forms\Domain\Repository\FormsRepository;
use App\Shared\Application\WorkflowTransaction;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreateFormHandler
{
    public function __construct(
        private FormsRepository $records,
        private WorkflowTransaction $transactions,
        private OccurrenceDirectory $occurrences,
        private GetFormHandler $getFormHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CreateFormCommand $message): ChurchForm
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $this->target($data['eventId'], $data['occurrenceId']);
            $form = new ChurchForm(...$data);
            $this->records->save($form);

            return ($this->getFormHandler)(new GetFormQuery($form->id));
        });
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
