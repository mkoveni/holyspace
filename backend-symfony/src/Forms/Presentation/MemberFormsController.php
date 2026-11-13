<?php

declare(strict_types=1);

namespace App\Forms\Presentation;

use App\Forms\Application\Command\SubmitMemberResponse\SubmitMemberResponseCommand;
use App\Forms\Application\Contract\LeaderboardEntry;
use App\Forms\Application\Contract\PublishedForm;
use App\Forms\Application\Query\GetLeaderboard\GetLeaderboardHandler;
use App\Forms\Application\Query\GetLeaderboard\GetLeaderboardQuery;
use App\Forms\Application\Query\GetMemberForm\GetMemberFormHandler;
use App\Forms\Application\Query\GetMemberForm\GetMemberFormQuery;
use App\Forms\Application\Query\ListMemberForms\ListMemberFormsHandler;
use App\Forms\Application\Query\ListMemberForms\ListMemberFormsQuery;
use App\Forms\Presentation\Request\SubmitRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Forms answered while signed in: members, staff with forms.respond, and members of chosen groups. Audience is rechecked by every handler. */
#[Route('/api/member/forms')]
final readonly class MemberFormsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListMemberFormsHandler $list,
        private GetMemberFormHandler $forms,
        private GetLeaderboardHandler $boards,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->list->handle(new ListMemberFormsQuery($this->account->requireAccount()));

        return FormJson::noStore(['items' => array_map(static fn (PublishedForm $f): array => [
            'id' => $f->id,
            'title' => $f->title,
            'description' => $f->description,
            'kind' => $f->kind,
            'identity' => $f->identity,
            'closesAt' => null === $f->closesAt ? null : Json::instant($f->closesAt),
        ], $items)]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(#[PathUuid] string $id): JsonResponse
    {
        return FormJson::noStore(FormJson::page($this->forms->handle(new GetMemberFormQuery($this->account->requireAccount(), $id))));
    }

    #[Route('/{id}/responses', methods: ['POST'])]
    public function submit(#[PathUuid] string $id, #[MapRequestPayload] SubmitRequest $b): JsonResponse
    {
        $result = $this->commands->dispatch(new SubmitMemberResponseCommand(
            $this->account->requireAccount(),
            $id,
            (int) $b->formVersion,
            (string) $b->submissionKey,
            $b->answers ?? [],
            $b->nickname,
        ));

        return FormJson::noStore(FormJson::result($result), 201);
    }

    #[Route('/{id}/leaderboard', methods: ['GET'])]
    public function leaderboard(#[PathUuid] string $id): JsonResponse
    {
        $entries = $this->boards->handle(GetLeaderboardQuery::forMember($this->account->requireAccount(), $id));

        return FormJson::noStore(['entries' => array_map(static fn (LeaderboardEntry $e): array => [
            'rank' => $e->rank, 'nickname' => $e->nickname, 'score' => $e->score, 'maxScore' => $e->maxScore,
        ], $entries)]);
    }
}
