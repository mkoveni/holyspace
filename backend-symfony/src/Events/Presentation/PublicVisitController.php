<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\SelfCheckInVisitor\SelfCheckInVisitorCommand;
use App\Events\Application\Query\GetVisitInvitation\GetVisitInvitationHandler;
use App\Events\Application\Query\GetVisitInvitation\GetVisitInvitationQuery;
use App\Events\Application\VisitorWelcome;
use App\Events\Domain\EventException;
use App\Events\Presentation\Request\SelfCheckInVisitorRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The visitor's side of self check-in. Both routes are public (`security.yaml`) and authenticated only by the
 * occurrence's link token. Throttled per client address and per link; responses are no-store.
 */
final readonly class PublicVisitController
{
    public function __construct(
        private CommandBus $commands,
        private GetVisitInvitationHandler $invitation,
        #[Autowire(service: 'limiter.visitor_check_in')]
        private RateLimiterFactoryInterface $perClient,
        #[Autowire(service: 'limiter.visitor_check_in_link')]
        private RateLimiterFactoryInterface $perLink,
    ) {
    }

    #[Route('/api/public/visits/{token}', methods: ['GET'])]
    public function invitation(string $token, Request $request): JsonResponse
    {
        $this->throttle($request, $token);
        $view = $this->invitation->handle(new GetVisitInvitationQuery($token));

        return new JsonResponse([
            'title' => $view->title,
            'venueName' => $view->venueName,
            'startsAt' => Json::instant($view->startsAt),
            'endsAt' => Json::instant($view->endsAt),
            'timeZone' => $view->timeZone,
            'open' => $view->open,
        ]);
    }

    #[Route('/api/public/visits/{token}', methods: ['POST'])]
    public function checkIn(string $token, Request $request, #[MapRequestPayload] SelfCheckInVisitorRequest $form): JsonResponse
    {
        $this->throttle($request, $token);
        if ('' !== trim((string) $form->website)) {
            // A bot filled the hidden field: look successful, save nothing.
            return new JsonResponse(['title' => '', 'admitted' => true], 201);
        }
        /** @var VisitorWelcome $welcome */
        $welcome = $this->commands->dispatch(new SelfCheckInVisitorCommand(
            $token,
            $form->firstName,
            $form->lastName,
            $form->mobile,
            $form->email,
            $form->howHeard,
            $form->partyAdults,
            $form->consent,
            $form->consentVersion,
            array_values($form->children),
        ));

        return new JsonResponse(['title' => $welcome->title, 'admitted' => $welcome->admitted], 201);
    }

    private function throttle(Request $request, string $token): void
    {
        $client = $this->perClient->create($request->getClientIp() ?? 'unknown')->consume();
        $link = $this->perLink->create(hash('sha256', $token))->consume();
        if (!$client->isAccepted() || !$link->isAccepted()) {
            throw EventException::throttled('Too many attempts. Please wait a minute and try again, or ask the welcome team.');
        }
    }
}
