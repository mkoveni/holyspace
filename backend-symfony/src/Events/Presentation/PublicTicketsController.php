<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Contract\TicketCatalog;
use App\Events\Application\PublicTicketOrders;
use App\Events\Application\TicketOrderStarter;
use App\Events\Domain\EventException;
use App\Events\Presentation\Request\TicketOrderRequest;
use App\Events\Presentation\Request\TicketTokenRequest;
use App\Payments\Application\Contract\GuestAccess;
use App\Shared\Application\HumanCheck;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public paid events: anyone may buy tickets without an account or a person record. Ordering is guarded by a captcha, a
 * honeypot and throttles per client, buyer email and event. A buyer reads their order and tickets only with a token sent in
 * the body (never a URL). Every response is `no-store`. Prices come from the server: the request carries only quantities.
 */
final readonly class PublicTicketsController
{
    public function __construct(
        private TicketCatalog $catalog,
        private TicketOrderStarter $starter,
        private PublicTicketOrders $orders,
        private GuestAccess $access,
        private HumanCheck $human,
        private ClockInterface $clock,
        #[Autowire(service: 'limiter.public_tickets')]
        private RateLimiterFactoryInterface $perClient,
        #[Autowire(service: 'limiter.public_tickets_email')]
        private RateLimiterFactoryInterface $perEmail,
        #[Autowire(service: 'limiter.public_tickets_event')]
        private RateLimiterFactoryInterface $perEvent,
        #[Autowire(service: 'limiter.public_tickets_read')]
        private RateLimiterFactoryInterface $reads,
    ) {
    }

    #[Route('/api/public/events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $this->read($request);

        return self::noStore(['events' => array_map(static fn (array $e): array => [
            'occurrenceId' => $e['occurrenceId'],
            'title' => $e['title'],
            'startsAt' => Json::instant($e['startsAt']),
            'endsAt' => Json::instant($e['endsAt']),
            'timeZone' => $e['timeZone'],
            'venueName' => $e['venueName'],
            'fromPriceMinor' => $e['fromPriceMinor'],
            'available' => $e['available'],
        ], $this->catalog->onSale($this->clock->now()))]);
    }

    #[Route('/api/public/events/{occurrenceId}', methods: ['GET'])]
    public function event(#[PathUuid] string $occurrenceId, Request $request): JsonResponse
    {
        $this->read($request);
        $e = $this->catalog->detail($occurrenceId, $this->clock->now()) ?? throw EventException::missing('Event');
        $fees = $this->orders->fees();

        return self::noStore([
            'occurrenceId' => $e['occurrenceId'],
            'title' => $e['title'],
            'description' => $e['description'],
            'startsAt' => Json::instant($e['startsAt']),
            'endsAt' => Json::instant($e['endsAt']),
            'timeZone' => $e['timeZone'],
            'venueName' => $e['venueName'],
            'venueAddress' => $e['venueAddress'],
            'maxPerOrder' => $e['maxPerOrder'],
            'available' => $e['available'],
            'ticketTypes' => $e['types'],
            // Shown so the buyer sees the processing fee as its own line before paying; the server alone computes the real one.
            'fees' => ['effectiveRatePpm' => $fees->effectiveRatePpm(), 'fixedMinor' => $fees->feeFixedMinor],
        ]);
    }

    #[Route('/api/public/events/{occurrenceId}/orders', methods: ['POST'])]
    public function order(#[PathUuid] string $occurrenceId, Request $request, #[MapRequestPayload] TicketOrderRequest $b): JsonResponse
    {
        $client = $request->getClientIp() ?? 'unknown';
        $email = TicketOrderStarter::email((string) $b->email);
        $this->throttle($client, $email, $occurrenceId);
        if ('' !== trim((string) $b->website)) {
            // A bot filled the hidden field: look successful, save nothing.
            return self::noStore(['id' => (string) $b->requestId, 'accessToken' => null, 'checkout' => null], 201);
        }
        if (!$this->human->passes($b->humanToken, $client)) {
            throw EventException::invalid('We could not confirm you are a person. Please try again.');
        }
        $quantities = [];
        foreach ((array) $b->quantities as $typeId => $quantity) {
            if (!\is_int($quantity)) {
                throw EventException::invalid('Choose how many of each ticket type you want.');
            }
            $quantities[(string) $typeId] = $quantity;
        }
        $started = $this->starter->start(strtolower((string) $b->requestId), $occurrenceId, $quantities, $b->name, $email);

        return self::noStore([
            'id' => $started->orderId,
            'accessToken' => $started->accessToken,
            'status' => $started->status,
            'totalMinor' => $started->totalMinor,
            'checkout' => null === $started->checkout ? null : ['id' => $started->checkout->id, 'url' => $started->checkout->url],
        ], 201);
    }

    /** The outcome for the buyer who holds the access token. Another order, a wrong token and an unknown one are all 404. */
    #[Route('/api/public/ticket-orders/status', methods: ['POST'])]
    public function status(Request $request, #[MapRequestPayload] TicketTokenRequest $b): JsonResponse
    {
        $this->read($request);
        $s = $this->orders->status($this->access->hash((string) $b->token));

        return self::noStore([
            'id' => $s['id'],
            'state' => $s['state'],
            'totalMinor' => $s['totalMinor'],
            'seats' => $s['seats'],
            'walletToken' => $s['walletToken'],
        ]);
    }

    /** The buyer's tickets, each with the text of its QR code, by wallet token. */
    #[Route('/api/public/ticket-orders/wallet', methods: ['POST'])]
    public function wallet(Request $request, #[MapRequestPayload] TicketTokenRequest $b): JsonResponse
    {
        $this->read($request);
        $w = $this->orders->wallet($this->access->hash((string) $b->token));

        return self::noStore([
            'orderId' => $w['orderId'],
            'state' => $w['state'],
            'event' => ['title' => $w['title'], 'startsAt' => Json::instant($w['startsAt']), 'endsAt' => Json::instant($w['endsAt']), 'timeZone' => $w['timeZone'], 'venueName' => $w['venueName'], 'venueAddress' => $w['venueAddress']],
            'subtotalMinor' => $w['subtotalMinor'],
            'feeMinor' => $w['feeMinor'],
            'totalMinor' => $w['totalMinor'],
            'tickets' => $w['tickets'],
        ]);
    }

    private function read(Request $request): void
    {
        if (!$this->reads->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw EventException::throttled('Too many requests. Please wait a minute and try again.');
        }
    }

    private function throttle(string $client, string $email, string $occurrenceId): void
    {
        if (!$this->perClient->create($client)->consume()->isAccepted()
            || !$this->perEmail->create($this->access->emailHash($email))->consume()->isAccepted()
            || !$this->perEvent->create($occurrenceId)->consume()->isAccepted()) {
            throw EventException::throttled('Too many attempts. Please wait a while and try again, or contact the church office.');
        }
    }

    /** @param array<string, mixed> $body */
    private static function noStore(array $body, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
