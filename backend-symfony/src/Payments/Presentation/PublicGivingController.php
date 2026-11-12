<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Command\StartGuestPayment\StartGuestPaymentCommand;
use App\Payments\Application\Command\StartGuestPayment\StartGuestPaymentHandler;
use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Application\Contract\HumanCheck;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Application\Query\GetGuestPayment\GetGuestPaymentHandler;
use App\Payments\Application\Query\GetGuestPayment\GetGuestPaymentQuery;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Presentation\Request\GuestPaymentRequest;
use App\Payments\Presentation\Request\GuestStatusRequest;
use App\Shared\Application\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public giving: anyone may give once online without an account or a person record. Authenticated only by the order's own
 * access token (returned once at checkout creation, kept by the page in sessionStorage, never placed in a URL); every
 * route is throttled and `no-store`. A bot that fills the hidden field is answered as if it succeeded and nothing is saved.
 */
#[Route('/api/public/giving')]
final readonly class PublicGivingController
{
    public function __construct(
        private CommandBus $commands,
        private ResumeCheckoutHandler $checkout,
        private GetGuestPaymentHandler $statuses,
        private OnlineFundDirectory $funds,
        private PaymentGateway $gateway,
        private GuestAccess $access,
        private HumanCheck $human,
        #[Autowire(service: 'limiter.public_giving')]
        private RateLimiterFactoryInterface $perClient,
        #[Autowire(service: 'limiter.public_giving_email')]
        private RateLimiterFactoryInterface $perEmail,
        #[Autowire(service: 'limiter.public_giving_read')]
        private RateLimiterFactoryInterface $reads,
    ) {
    }

    /** What may be given to and how: active funds enabled for online giving, and providers that are configured. */
    #[Route('', methods: ['GET'])]
    public function options(Request $request): JsonResponse
    {
        $this->read($request);
        $funds = array_values(array_filter($this->funds->funds(false), static fn (OnlineFund $f): bool => $f->active && $f->enabled));

        return self::noStore([
            'funds' => array_map(static fn (OnlineFund $f): array => ['id' => $f->id, 'name' => $f->name, 'description' => $f->description], $funds),
            'providers' => array_values(array_map(
                static fn (PaymentProvider $p): array => ['id' => $p->id, 'name' => $p->name],
                array_filter($this->gateway->providers(), static fn (PaymentProvider $p): bool => $p->available),
            )),
            'minimumAmountMinor' => 500,
        ]);
    }

    #[Route('/checkouts', methods: ['POST'])]
    public function start(Request $request, #[MapRequestPayload] GuestPaymentRequest $b): JsonResponse
    {
        $client = $request->getClientIp() ?? 'unknown';
        $email = StartGuestPaymentHandler::email((string) $b->email);
        $this->throttle($client, $email);
        if ('' !== trim((string) $b->website)) {
            // A bot filled the hidden field: look successful, save nothing.
            return self::noStore(['id' => (string) $b->requestId, 'accessToken' => null, 'checkout' => null], 201);
        }
        if (!$this->human->passes($b->humanToken, $client)) {
            throw PaymentException::invalid('We could not confirm you are a person. Please try again.');
        }
        $id = $this->commands->dispatch(new StartGuestPaymentCommand(
            strtolower((string) $b->requestId),
            strtolower((string) $b->fundId),
            (int) $b->amountMinor,
            (string) $b->provider,
            $b->name,
            $email,
        ));
        $checkout = $this->checkout->handle(new ResumeCheckoutCommand(PaymentOrder::GUEST_ACTOR, (string) $id, false, true));

        return self::noStore([
            'id' => $id,
            'accessToken' => $this->access->token((string) $id),
            'checkout' => PaymentJson::checkout($checkout),
        ], 201);
    }

    /** The outcome for the giver who holds the token. Another order, a wrong token and an unknown one are all 404. */
    #[Route('/orders/status', methods: ['POST'])]
    public function status(Request $request, #[MapRequestPayload] GuestStatusRequest $b): JsonResponse
    {
        $this->read($request);
        $s = $this->statuses->handle(new GetGuestPaymentQuery((string) $b->accessToken));

        return self::noStore(['id' => $s->orderId, 'state' => $s->state, 'amountMinor' => $s->amountMinor, 'confirmationQueued' => $s->confirmationQueued]);
    }

    private function read(Request $request): void
    {
        if (!$this->reads->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw PaymentException::throttled('Too many requests. Please wait a minute and try again.');
        }
    }

    private function throttle(string $client, string $email): void
    {
        if (!$this->perClient->create($client)->consume()->isAccepted() || !$this->perEmail->create($this->access->emailHash($email))->consume()->isAccepted()) {
            throw PaymentException::throttled('Too many attempts. Please wait a while and try again, or contact the church office.');
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
