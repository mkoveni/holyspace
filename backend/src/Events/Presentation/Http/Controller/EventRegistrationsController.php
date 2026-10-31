<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\Controller;

use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationCommand;
use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationHandler;
use App\Events\Application\Command\ConfigureRegistration\ConfigureRegistrationCommand;
use App\Events\Application\Command\ConfigureRegistration\ConfigureRegistrationHandler;
use App\Events\Application\Command\ConfigureTicketPrice\ConfigureTicketPriceCommand;
use App\Events\Application\Command\ConfigureTicketPrice\ConfigureTicketPriceHandler;
use App\Events\Application\Command\RegisterForEvent\RegisterForEventCommand;
use App\Events\Application\Command\RegisterForEvent\RegisterForEventHandler;
use App\Events\Application\Query\GetRegistrationOverview\GetRegistrationOverviewHandler;
use App\Events\Application\Query\GetRegistrationOverview\GetRegistrationOverviewQuery;
use App\Events\Presentation\Http\DTO\CancelRegistrationPayload;
use App\Events\Presentation\Http\DTO\RegisterPersonPayload;
use App\Events\Presentation\Http\DTO\RegistrationFilter;
use App\Events\Presentation\Http\DTO\RegistrationSettingsPayload;
use App\Events\Presentation\Http\DTO\TicketPricePayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/events/{eventId}/occurrences/{occurrenceId}/registrations', format: 'json')]
final class EventRegistrationsController extends WorkflowController
{
    public function __construct(
        private readonly GetRegistrationOverviewHandler $getRegistrationOverviewHandler,
        private readonly ConfigureRegistrationHandler $configureRegistrationHandler,
        private readonly RegisterForEventHandler $registerForEventHandler,
        private readonly ConfigureTicketPriceHandler $configureTicketPriceHandler,
        private readonly CancelEventRegistrationHandler $cancelEventRegistrationHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[IsGranted('events.read')]
    public function overview(
        string $eventId,
        string $occurrenceId,
        #[MapQueryString(validationFailedStatusCode: 422)]
        RegistrationFilter $filter = new RegistrationFilter(),
    ): JsonResponse {
        return $this->result(fn () => ($this->getRegistrationOverviewHandler)(new GetRegistrationOverviewQuery($eventId, $occurrenceId, $filter->after, $filter->limit)));
    }

    #[Route('/settings', methods: ['PUT'])]
    #[IsGranted('events.manage')]
    public function settings(
        string $eventId,
        string $occurrenceId,
        #[MapRequestPayload]
        RegistrationSettingsPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->configureRegistrationHandler)(new ConfigureRegistrationCommand($eventId, $occurrenceId, $p->open, $p->capacity, $p->waitlistEnabled, $p->expectedVersion)));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('events.manage')]
    public function register(
        string $eventId,
        string $occurrenceId,
        #[MapRequestPayload]
        RegisterPersonPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->registerForEventHandler)(new RegisterForEventCommand($eventId, $occurrenceId, $p->personId)), 201);
    }

    #[Route('/ticket-price', methods: ['PUT'])]
    #[IsGranted('events.manage')]
    public function price(
        string $eventId,
        string $occurrenceId,
        #[MapRequestPayload]
        TicketPricePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->configureTicketPriceHandler)(new ConfigureTicketPriceCommand($eventId, $occurrenceId, $p->amountMinor, $p->expectedVersion)));
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    #[IsGranted('events.manage')]
    public function cancel(
        string $eventId,
        string $occurrenceId,
        string $id,
        #[MapRequestPayload]
        CancelRegistrationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->cancelEventRegistrationHandler)(new CancelEventRegistrationCommand($eventId, $occurrenceId, $id, $p->reason, $p->expectedVersion)));
    }
}
