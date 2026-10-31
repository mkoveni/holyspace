<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\Controller;

use App\Forms\Application\Command\CreateForm\CreateFormCommand;
use App\Forms\Application\Command\CreateForm\CreateFormHandler;
use App\Forms\Application\Command\EditForm\EditFormCommand;
use App\Forms\Application\Command\EditForm\EditFormHandler;
use App\Forms\Application\Command\PublishForm\PublishFormCommand;
use App\Forms\Application\Command\PublishForm\PublishFormHandler;
use App\Forms\Application\Command\ReviewSubmission\ReviewSubmissionCommand;
use App\Forms\Application\Command\ReviewSubmission\ReviewSubmissionHandler;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Application\Query\GetFormSubmission\GetFormSubmissionHandler;
use App\Forms\Application\Query\GetFormSubmission\GetFormSubmissionQuery;
use App\Forms\Application\Query\ListFormsRecords\ListFormsRecordsHandler;
use App\Forms\Application\Query\ListFormsRecords\ListFormsRecordsQuery;
use App\Forms\Presentation\Http\DTO\FormPayload;
use App\Forms\Presentation\Http\DTO\PageFilter;
use App\Forms\Presentation\Http\DTO\PublishPayload;
use App\Forms\Presentation\Http\DTO\ReviewPayload;
use App\Shared\Presentation\Http\WorkflowController;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/forms', format: 'json')]
final class FormsController extends WorkflowController
{
    public function __construct(
        private readonly ListFormsRecordsHandler $listFormsRecordsHandler,
        private readonly GetFormHandler $getFormHandler,
        private readonly CreateFormHandler $createFormHandler,
        private readonly EditFormHandler $editFormHandler,
        private readonly PublishFormHandler $publishFormHandler,
        private readonly GetFormSubmissionHandler $getFormSubmissionHandler,
        private readonly ReviewSubmissionHandler $reviewSubmissionHandler,
    ) {
    }

    #[Route('', methods: ['GET']), IsGranted('forms.read')]
    public function list(
        #[MapQueryString(validationFailedStatusCode: 422)]
        PageFilter $filter = new PageFilter(),
    ): JsonResponse {
        return $this->result(fn () => ($this->listFormsRecordsHandler)(new ListFormsRecordsQuery('forms', null, $filter->after, $filter->limit)));
    }

    #[Route('/{id}', methods: ['GET']), IsGranted('forms.read')]
    public function get(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getFormHandler)(new GetFormQuery($id)));
    }

    #[Route('', methods: ['POST']), IsGranted('forms.manage')]
    public function create(
        #[MapRequestPayload]
        FormPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createFormHandler)(new CreateFormCommand($this->data($p))), 201);
    }

    #[Route('/{id}', methods: ['PUT']), IsGranted('forms.manage')]
    public function edit(
        string $id,
        #[MapRequestPayload]
        FormPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->editFormHandler)(new EditFormCommand($id, $this->data($p), $p->expectedVersion ?? throw new InvalidArgumentException('Expected version is required.'))));
    }

    #[Route('/{id}/publication', methods: ['PUT']), IsGranted('forms.manage')]
    public function publish(
        string $id,
        #[MapRequestPayload]
        PublishPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->publishFormHandler)(new PublishFormCommand($id, $p->published, $p->expectedVersion)));
    }

    #[Route('/{id}/submissions', methods: ['GET']), IsGranted('forms.manage')]
    public function submissions(
        string $id,
        #[MapQueryString(validationFailedStatusCode: 422)]
        PageFilter $filter = new PageFilter(),
    ): JsonResponse {
        return $this->result(fn () => ($this->listFormsRecordsHandler)(new ListFormsRecordsQuery('submissions', $id, $filter->after, $filter->limit)));
    }

    #[Route('/submissions/{id}/review', methods: ['PUT']), IsGranted('forms.manage')]
    public function review(
        string $id,
        #[MapRequestPayload]
        ReviewPayload $p,
    ): JsonResponse {
        return $this->result(function () use ($id, $p) {
            $submission = ($this->getFormSubmissionHandler)(new GetFormSubmissionQuery($id));
            if (null !== $p->personId) {
                $this->denyAccessUnlessGranted('people.read');
            }
            if ('approved' === $p->status && null !== $submission->snapshot['eventId']) {
                $this->denyAccessUnlessGranted('events.manage');
            }

            return ($this->reviewSubmissionHandler)(new ReviewSubmissionCommand($id, $p->status, $p->note, $p->personId, $p->expectedVersion));
        });
    }

    private function data(FormPayload $p): array
    {
        return ['title' => $p->title, 'fields' => $p->fields, 'description' => $p->description, 'eventId' => $p->eventId, 'occurrenceId' => $p->occurrenceId];
    }
}
