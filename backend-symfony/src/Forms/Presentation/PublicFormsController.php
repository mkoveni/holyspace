<?php

declare(strict_types=1);

namespace App\Forms\Presentation;

use App\Forms\Application\Command\SubmitPublicResponse\SubmitPublicResponseCommand;
use App\Forms\Application\Contract\FormKeys;
use App\Forms\Application\Contract\LeaderboardEntry;
use App\Forms\Application\Query\GetLeaderboard\GetLeaderboardHandler;
use App\Forms\Application\Query\GetLeaderboard\GetLeaderboardQuery;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormHandler;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormQuery;
use App\Forms\Domain\FormException;
use App\Forms\Presentation\Request\SubmitRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Application\HumanCheck;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public forms and quizzes. The link token is the only credential; no account, person or IP address is stored. Reads and
 * submits are throttled per client and per form, submits need a human check, and a bot that fills the hidden field is
 * told it succeeded while nothing is saved.
 */
#[Route('/api/public/forms/{token}', requirements: ['token' => '[A-Za-z0-9_-]{20,100}'])]
final readonly class PublicFormsController
{
    public function __construct(
        private CommandBus $commands,
        private GetPublicFormHandler $forms,
        private GetLeaderboardHandler $boards,
        private FormKeys $keys,
        private HumanCheck $human,
        #[Autowire(service: 'limiter.public_forms')]
        private RateLimiterFactoryInterface $submits,
        #[Autowire(service: 'limiter.public_forms_form')]
        private RateLimiterFactoryInterface $perForm,
        #[Autowire(service: 'limiter.public_forms_read')]
        private RateLimiterFactoryInterface $reads,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function show(Request $request, string $token): JsonResponse
    {
        $this->read($request);

        return FormJson::noStore(FormJson::page($this->forms->handle(new GetPublicFormQuery($token))));
    }

    #[Route('', methods: ['POST'])]
    public function submit(Request $request, string $token, #[MapRequestPayload] SubmitRequest $b): JsonResponse
    {
        $client = $request->getClientIp() ?? 'unknown';
        if (!$this->submits->create($client)->consume()->isAccepted() || !$this->perForm->create($this->keys->tokenHash($token))->consume()->isAccepted()) {
            throw FormException::throttled('Too many answers. Please wait a while and try again.');
        }
        if ('' !== trim((string) $b->website)) {
            return FormJson::noStore(['received' => true, 'score' => null, 'maxScore' => null, 'answers' => null], 201);
        }
        if (!$this->human->passes($b->humanToken, $client)) {
            throw FormException::invalid('We could not confirm you are a person. Please try again.');
        }
        $result = $this->commands->dispatch(new SubmitPublicResponseCommand(
            $token,
            (int) $b->formVersion,
            (string) $b->challenge,
            (string) $b->submissionKey,
            $b->answers ?? [],
            $b->nickname,
        ));

        return FormJson::noStore(FormJson::result($result), 201);
    }

    #[Route('/leaderboard', methods: ['GET'])]
    public function leaderboard(Request $request, string $token): JsonResponse
    {
        $this->read($request);
        $entries = $this->boards->handle(GetLeaderboardQuery::forPublic($token));

        return FormJson::noStore(['entries' => array_map(static fn (LeaderboardEntry $e): array => [
            'rank' => $e->rank, 'nickname' => $e->nickname, 'score' => $e->score, 'maxScore' => $e->maxScore,
        ], $entries)]);
    }

    private function read(Request $request): void
    {
        if (!$this->reads->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw FormException::throttled('Too many requests. Please wait a minute and try again.');
        }
    }
}
