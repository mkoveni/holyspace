<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReviewSubmission;

use App\Events\Application\Command\RegisterForEvent\RegisterForEventCommand;
use App\Events\Application\Command\RegisterForEvent\RegisterForEventHandler;
use App\Forms\Application\Query\GetFormSubmission\GetFormSubmissionHandler;
use App\Forms\Application\Query\GetFormSubmission\GetFormSubmissionQuery;
use App\Forms\Domain\Model\FormSubmission;
use App\Forms\Domain\Repository\FormsRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReviewSubmissionHandler
{
    public function __construct(
        private FormsRepository $records,
        private WorkflowTransaction $transactions,
        private PersonDirectory $people,
        private GetFormSubmissionHandler $getFormSubmissionHandler,
        private RegisterForEventHandler $registerForEventHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ReviewSubmissionCommand $message): FormSubmission
    {
        $id = $message->id;
        $status = $message->status;
        $note = $message->note;
        $personId = $message->personId;
        $version = $message->version;

        return $this->transactions->run(function () use ($id, $status, $note, $personId, $version) {
            $submission = ($this->getFormSubmissionHandler)(new GetFormSubmissionQuery($id));
            $this->version($submission->version, $version);
            // Validate the decision before any event booking is written.
            $submission->review($status, $note, $personId, null);
            if ('rejected' === $status && null !== $personId) {
                throw new InvalidArgumentException('Rejected responses cannot link a person.');
            }
            if (null !== $personId && !$this->people->get($personId)) {
                throw new InvalidArgumentException('Person not found.');
            }
            $registrationId = null;
            if ('approved' === $status && null !== $submission->snapshot['eventId']) {
                if (null === $personId) {
                    throw new InvalidArgumentException('Select the person before approving an event booking.');
                }
                $registrationId = ($this->registerForEventHandler)(new RegisterForEventCommand($submission->snapshot['eventId'], $submission->snapshot['occurrenceId'], $personId))->id;
            }
            $this->records->save($submission->review($status, $note, $personId, $registrationId));

            return ($this->getFormSubmissionHandler)(new GetFormSubmissionQuery($id));
        });
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This record changed. Reload and review before retrying.');
        }
    }
}
