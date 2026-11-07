<?php

declare(strict_types=1);

namespace App\Communications\Presentation;

use App\Communications\Application\Command\ArchiveTemplate\ArchiveTemplateCommand;
use App\Communications\Application\Command\CreateTemplate\CreateTemplateCommand;
use App\Communications\Application\Command\PublishTemplate\PublishTemplateCommand;
use App\Communications\Application\Command\UpdateTemplate\UpdateTemplateCommand;
use App\Communications\Application\Contract\TemplateRevision;
use App\Communications\Application\Contract\TemplateView;
use App\Communications\Application\Query\GetTemplate\GetTemplateHandler;
use App\Communications\Application\Query\GetTemplate\GetTemplateQuery;
use App\Communications\Application\Query\ListTemplateRevisions\ListTemplateRevisionsHandler;
use App\Communications\Application\Query\ListTemplateRevisions\ListTemplateRevisionsQuery;
use App\Communications\Application\Query\ListTemplates\ListTemplatesHandler;
use App\Communications\Application\Query\ListTemplates\ListTemplatesQuery;
use App\Communications\Application\Query\PreviewTemplate\PreviewTemplateHandler;
use App\Communications\Application\Query\PreviewTemplate\PreviewTemplateQuery;
use App\Communications\Presentation\Request\ChangeTemplateRequest;
use App\Communications\Presentation\Request\DraftTemplateRequest;
use App\Communications\Presentation\Request\EditTemplateRequest;
use App\Communications\Presentation\Request\PreviewTemplateRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Email templates: browse and preview with communications.templates.read, change with communications.templates.manage. */
#[Route('/api/communications/templates')]
final readonly class TemplateController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListTemplatesHandler $list,
        private GetTemplateHandler $get,
        private ListTemplateRevisionsHandler $revisions,
        private PreviewTemplateHandler $preview,
    ) {
    }

    #[IsGranted('communications.templates.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListTemplatesQuery($search, $limit, $offset));

        return new JsonResponse(['templates' => array_map(self::view(...), $page->templates), 'total' => $page->total]);
    }

    #[IsGranted('communications.templates.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] DraftTemplateRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateTemplateCommand(
            $this->account->requireAccount(),
            $request->key ?? throw new BadRequestException('Key is required.'),
            $request->name ?? throw new BadRequestException('Name is required.'),
            $request->subject ?? throw new BadRequestException('Subject is required.'),
            $request->html ?? throw new BadRequestException('Body is required.'),
            $request->variables ?? throw new BadRequestException('Variables are required.'),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('communications.templates.read')]
    #[Route('/preview', methods: ['POST'])]
    public function preview(#[MapRequestPayload] PreviewTemplateRequest $request): JsonResponse
    {
        $rendered = $this->preview->handle(new PreviewTemplateQuery(
            $request->subject ?? throw new BadRequestException('Subject is required.'),
            $request->html ?? throw new BadRequestException('Body is required.'),
            $request->variables ?? throw new BadRequestException('Variables are required.'),
            array_map('strval', $request->values ?? throw new BadRequestException('Values are required.')),
        ));

        return new JsonResponse(['subject' => $rendered->subject, 'html' => $rendered->html]);
    }

    #[IsGranted('communications.templates.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(self::view($this->get->handle(new GetTemplateQuery($id))));
    }

    #[IsGranted('communications.templates.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] EditTemplateRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new UpdateTemplateCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->name ?? throw new BadRequestException('Name is required.'),
            $request->subject ?? throw new BadRequestException('Subject is required.'),
            $request->html ?? throw new BadRequestException('Body is required.'),
            $request->variables ?? throw new BadRequestException('Variables are required.'),
        ))]);
    }

    #[IsGranted('communications.templates.manage')]
    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(#[PathUuid] string $id, #[MapRequestPayload] ChangeTemplateRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new PublishTemplateCommand($this->account->requireAccount(), $id, $request->expectedVersion))]);
    }

    #[IsGranted('communications.templates.manage')]
    #[Route('/{id}/archive', methods: ['POST'])]
    public function archive(#[PathUuid] string $id, #[MapRequestPayload] ChangeTemplateRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new ArchiveTemplateCommand($this->account->requireAccount(), $id, $request->expectedVersion))]);
    }

    #[IsGranted('communications.templates.read')]
    #[Route('/{id}/revisions', methods: ['GET'])]
    public function revisions(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(static fn (TemplateRevision $revision): array => [
            'version' => $revision->version,
            'subject' => $revision->subject,
            'html' => $revision->html,
            'variables' => $revision->variables,
            'publishedAt' => Json::instant($revision->publishedAt),
        ], $this->revisions->handle(new ListTemplateRevisionsQuery($id, $limit, $offset))));
    }

    /** @return array<string, mixed> */
    private static function view(TemplateView $view): array
    {
        return [
            'id' => $view->id,
            'key' => $view->key,
            'name' => $view->name,
            'subject' => $view->subject,
            'html' => $view->html,
            'variables' => $view->variables,
            'status' => $view->status,
            'version' => $view->version,
            'publishedVersion' => $view->publishedVersion,
            'updatedAt' => Json::instant($view->updatedAt),
        ];
    }
}
