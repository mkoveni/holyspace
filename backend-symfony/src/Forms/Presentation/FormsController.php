<?php

declare(strict_types=1);

namespace App\Forms\Presentation;

use App\Forms\Application\Command\CloseForm\CloseFormCommand;
use App\Forms\Application\Command\CreateForm\CreateFormCommand;
use App\Forms\Application\Command\ExportResponses\ExportResponsesCommand;
use App\Forms\Application\Command\HideResponse\HideResponseCommand;
use App\Forms\Application\Command\PublishForm\PublishFormCommand;
use App\Forms\Application\Command\ReadResponses\ReadResponsesCommand;
use App\Forms\Application\Command\ReopenForm\ReopenFormCommand;
use App\Forms\Application\Command\UnpublishForm\UnpublishFormCommand;
use App\Forms\Application\Command\UpdateForm\UpdateFormCommand;
use App\Forms\Application\Contract\FormSummary;
use App\Forms\Application\Contract\ResponseView;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Application\Query\GetSummary\GetSummaryHandler;
use App\Forms\Application\Query\GetSummary\GetSummaryQuery;
use App\Forms\Application\Query\ListForms\ListFormsHandler;
use App\Forms\Application\Query\ListForms\ListFormsQuery;
use App\Forms\Presentation\Request\FormRequest;
use App\Forms\Presentation\Request\VersionRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff management of forms and quizzes, and reading their responses. Handlers check each permission themselves. */
#[Route('/api/forms')]
final readonly class FormsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListFormsHandler $list,
        private GetFormHandler $forms,
        private GetSummaryHandler $summaries,
    ) {
    }

    #[IsGranted('forms.read|forms.manage')]
    #[Route('', methods: ['GET'])]
    public function index(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(['items' => array_map(static fn (FormSummary $f): array => [
            'id' => $f->id, 'title' => $f->title, 'kind' => $f->kind, 'audience' => $f->audience, 'identity' => $f->identity,
            'status' => $f->status, 'responseCount' => $f->responseCount, 'version' => $f->version,
        ], $this->list->handle(new ListFormsQuery($limit, $offset)))]);
    }

    #[IsGranted('forms.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] FormRequest $b): JsonResponse
    {
        if (null === $b->requestId) {
            throw new BadRequestException('requestId is required.');
        }
        $id = $this->commands->dispatch(new CreateFormCommand($this->account->requireAccount(), strtolower($b->requestId), FormJson::draft($b)));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('forms.read|forms.manage')]
    #[Route('/{id}', methods: ['GET'])]
    public function show(#[PathUuid] string $id): JsonResponse
    {
        $f = $this->forms->handle(new GetFormQuery($id));

        return new JsonResponse([
            'id' => $f->id, 'title' => $f->title, 'description' => $f->description, 'kind' => $f->kind, 'audience' => $f->audience,
            'identity' => $f->identity, 'status' => $f->status,
            'opensAt' => null === $f->opensAt ? null : Json::instant($f->opensAt),
            'closesAt' => null === $f->closesAt ? null : Json::instant($f->closesAt),
            'maxResponses' => $f->maxResponses, 'retentionMonths' => $f->retentionMonths,
            'leaderboardEnabled' => $f->leaderboardEnabled, 'leaderboardSize' => $f->leaderboardSize,
            'showScore' => $f->showScore, 'showAnswers' => $f->showAnswers, 'groupIds' => $f->groupIds,
            'definition' => $f->draft, 'publishedVersion' => $f->publishedVersion, 'responseCount' => $f->responseCount,
            'nextPurgeOn' => $f->nextPurgeOn?->format('Y-m-d'), 'version' => $f->version,
        ]);
    }

    #[IsGranted('forms.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] FormRequest $b): Response
    {
        if (null === $b->expectedVersion) {
            throw new BadRequestException('expectedVersion is required.');
        }
        $this->commands->dispatch(new UpdateFormCommand($this->account->requireAccount(), $id, $b->expectedVersion, FormJson::draft($b)));

        return new Response(status: 204);
    }

    /** Returns the public link token once for a public form; only its hash is stored. */
    #[IsGranted('forms.manage')]
    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(#[PathUuid] string $id, #[MapRequestPayload] VersionRequest $b): JsonResponse
    {
        $token = $this->commands->dispatch(new PublishFormCommand($this->account->requireAccount(), $id, $b->expectedVersion));

        return FormJson::noStore(['token' => $token]);
    }

    #[IsGranted('forms.manage')]
    #[Route('/{id}/close', methods: ['POST'])]
    public function close(#[PathUuid] string $id, #[MapRequestPayload] VersionRequest $b): Response
    {
        $this->commands->dispatch(new CloseFormCommand($this->account->requireAccount(), $id, $b->expectedVersion));

        return new Response(status: 204);
    }

    #[IsGranted('forms.manage')]
    #[Route('/{id}/reopen', methods: ['POST'])]
    public function reopen(#[PathUuid] string $id, #[MapRequestPayload] VersionRequest $b): Response
    {
        $this->commands->dispatch(new ReopenFormCommand($this->account->requireAccount(), $id, $b->expectedVersion));

        return new Response(status: 204);
    }

    #[IsGranted('forms.manage')]
    #[Route('/{id}/unpublish', methods: ['POST'])]
    public function unpublish(#[PathUuid] string $id, #[MapRequestPayload] VersionRequest $b): Response
    {
        $this->commands->dispatch(new UnpublishFormCommand($this->account->requireAccount(), $id, $b->expectedVersion));

        return new Response(status: 204);
    }

    #[IsGranted('forms.responses.read')]
    #[Route('/{id}/responses', methods: ['GET'])]
    public function responses(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->commands->dispatch(new ReadResponsesCommand($this->account->requireAccount(), $id, $limit, $offset));

        return FormJson::noStore([
            'total' => $page->total,
            'identified' => $page->identified,
            'items' => array_map(static fn (ResponseView $r): array => [
                'id' => $r->id, 'formVersion' => $r->formVersion, 'submittedOn' => $r->submittedOn->format('Y-m-d'),
                'submittedAt' => null === $r->submittedAt ? null : Json::instant($r->submittedAt),
                'personId' => $r->personId, 'nickname' => $r->nickname, 'score' => $r->score, 'maxScore' => $r->maxScore,
                'hidden' => $r->hidden, 'answers' => $r->answers,
            ], $page->items),
        ]);
    }

    #[IsGranted('forms.responses.read')]
    #[Route('/{id}/summary', methods: ['GET'])]
    public function summary(#[PathUuid] string $id): JsonResponse
    {
        $s = $this->summaries->handle(new GetSummaryQuery($id));

        return FormJson::noStore(['responses' => $s->responses, 'hidden' => $s->hidden, 'minimumForAnonymous' => $s->minimumForAnonymous, 'questions' => $s->questions]);
    }

    #[IsGranted('forms.responses.read')]
    #[Route('/{id}/export', methods: ['GET'])]
    public function export(#[PathUuid] string $id): Response
    {
        $export = $this->commands->dispatch(new ExportResponsesCommand($this->account->requireAccount(), $id));
        $header = ['Submitted', 'Version', ...($export->identified ? ['Person'] : []), 'Nickname', 'Score', 'Out of', 'Hidden', ...array_values($export->columns)];
        $out = fopen('php://temp', 'w+');
        \assert(false !== $out);
        // A leading =, +, - or @ makes a spreadsheet run the cell as a formula: prefix such text with a quote.
        $cell = static function (mixed $v): string {
            $text = is_array($v) ? implode('; ', array_map('strval', $v)) : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v);

            return 1 === preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
        };
        fputcsv($out, array_map($cell, $header), escape: '');
        foreach ($export->items as $r) {
            $row = [$r->submittedAt?->format('Y-m-d H:i') ?? $r->submittedOn->format('Y-m-d'), $r->formVersion];
            if ($export->identified) {
                $row[] = $r->personId ?? '';
            }
            array_push($row, $r->nickname ?? '', $r->score ?? '', $r->maxScore ?? '', $r->hidden ? 'yes' : 'no');
            foreach (array_keys($export->columns) as $qid) {
                $row[] = $r->answers[$qid] ?? '';
            }
            fputcsv($out, array_map($cell, $row), escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="form-responses.csv"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[IsGranted('forms.responses.moderate')]
    #[Route('/{id}/responses/{responseId}/hide', methods: ['POST'])]
    public function hide(#[PathUuid] string $id, #[PathUuid] string $responseId): Response
    {
        $this->commands->dispatch(new HideResponseCommand($this->account->requireAccount(), $id, $responseId));

        return new Response(status: 204);
    }
}
