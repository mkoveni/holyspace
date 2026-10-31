<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitPublicForm;

use App\Forms\Application\Query\GetPublicForm\GetPublicFormHandler;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormQuery;
use App\Forms\Domain\Model\FormSubmission;
use App\Forms\Domain\Repository\FormsRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SubmitPublicFormHandler
{
    public function __construct(
        private FormsRepository $records,
        private WorkflowTransaction $transactions,
        private GetPublicFormHandler $getPublicFormHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SubmitPublicFormCommand $message): void
    {
        $token = $message->token;
        $version = $message->version;
        $answers = $message->answers;
        $submissionKey = $message->submissionKey;
        $this->transactions->run(function () use ($token, $version, $answers, $submissionKey) {
            $form = ($this->getPublicFormHandler)(new GetPublicFormQuery($token));
            Input::id($submissionKey);
            $clean = $form->answers($answers);
            $previous = $this->records->bySubmissionKey($form->id, $submissionKey);
            if ($previous) {
                if ($previous->answers !== $clean || $previous->formVersion !== $version) {
                    throw new DomainException('This submission reference has already been used.');
                }

                return;
            }
            $this->version($form->version, $version);
            $snapshot = ['title' => $form->title, 'description' => $form->description, 'fields' => $form->fields, 'eventId' => $form->eventId, 'occurrenceId' => $form->occurrenceId];
            $this->records->save(new FormSubmission($form->id, $version, $snapshot, $clean, $submissionKey));
        });
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This record changed. Reload and review before retrying.');
        }
    }
}
