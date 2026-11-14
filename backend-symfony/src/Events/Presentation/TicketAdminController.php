<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\ConfigurePublicSale\ConfigurePublicSaleCommand;
use App\Events\Application\Command\ConfigureTicketFees\ConfigureTicketFeesCommand;
use App\Events\Application\Command\SaveTicketType\SaveTicketTypeCommand;
use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Presentation\Request\ConfigurePublicSaleRequest;
use App\Events\Presentation\Request\ConfigureTicketFeesRequest;
use App\Events\Presentation\Request\SaveTicketTypeRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Payments\Application\Contract\PayloadVault;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff side of public ticketing. Buyer contact details need the separate events.tickets.contacts permission. */
final readonly class TicketAdminController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private TicketRepository $tickets,
        private TicketCatalog $catalog,
        private PermissionChecker $permissions,
        private PayloadVault $vault,
    ) {
    }

    #[IsGranted('events.tickets.read')]
    #[Route('/api/events/occurrences/{id}/public-sales', methods: ['GET'])]
    public function sale(#[PathUuid] string $id): JsonResponse
    {
        $sale = $this->tickets->sale($id);

        return self::noStore([
            'enabled' => $sale->enabled ?? false,
            'opensAt' => null === $sale?->opensAt ? null : Json::instant($sale->opensAt),
            'closesAt' => null === $sale?->closesAt ? null : Json::instant($sale->closesAt),
            'maxPerOrder' => $sale->maxPerOrder ?? 10,
            'version' => $sale->version ?? 0,
            'ticketTypes' => array_map(static fn ($t): array => [
                'id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'priceMinor' => $t->priceMinor,
                'quantityCap' => $t->quantityCap, 'sortOrder' => $t->sortOrder, 'active' => $t->active, 'version' => $t->version,
            ], $this->tickets->types($id)),
        ]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/events/occurrences/{id}/public-sales', methods: ['PUT'])]
    public function configure(#[PathUuid] string $id, #[MapRequestPayload] ConfigurePublicSaleRequest $r): Response
    {
        $this->commands->dispatch(new ConfigurePublicSaleCommand(
            $this->account->requireAccount(),
            $id,
            $r->expectedVersion,
            $r->enabled,
            null === $r->opensAt ? null : Json::parseInstant($r->opensAt),
            null === $r->closesAt ? null : Json::parseInstant($r->closesAt),
            $r->maxPerOrder,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/events/occurrences/{id}/ticket-types', methods: ['POST'])]
    public function create(#[PathUuid] string $id, #[MapRequestPayload] SaveTicketTypeRequest $r): JsonResponse
    {
        return new JsonResponse(['id' => $this->save($id, null, $r)], 201);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/events/occurrences/{id}/ticket-types/{typeId}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[PathUuid] string $typeId, #[MapRequestPayload] SaveTicketTypeRequest $r): JsonResponse
    {
        return new JsonResponse(['id' => $this->save($id, $typeId, $r)]);
    }

    #[IsGranted('events.tickets.read')]
    #[Route('/api/events/occurrences/{id}/ticket-orders', methods: ['GET'])]
    public function orders(#[PathUuid] string $id, #[MapQueryParameter] int $limit = 100): JsonResponse
    {
        $contacts = $this->permissions->hasPermission('events.tickets.contacts');

        return self::noStore(['orders' => array_map(fn (array $o): array => [
            'id' => $o['id'],
            'status' => $o['status'],
            'seats' => $o['seats'],
            'totalMinor' => $o['totalMinor'],
            'createdAt' => Json::instant($o['createdAt']),
            'buyerName' => $contacts ? $o['buyerName'] : null,
            'buyerEmail' => $contacts ? $this->vault->decrypt($o['buyerEmailEncrypted']) : null,
        ], $this->catalog->orders($id, $limit))]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/ticket-fees', methods: ['GET'])]
    public function fees(): JsonResponse
    {
        $f = $this->tickets->fees();

        return self::noStore(['feeRatePpm' => $f->feeRatePpm, 'feeFixedMinor' => $f->feeFixedMinor, 'providerVatPpm' => $f->providerVatPpm, 'holdMinutes' => $f->holdMinutes, 'effectiveRatePpm' => $f->effectiveRatePpm(), 'version' => $f->version]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/ticket-fees', methods: ['PUT'])]
    public function configureFees(#[MapRequestPayload] ConfigureTicketFeesRequest $r): Response
    {
        $this->commands->dispatch(new ConfigureTicketFeesCommand($this->account->requireAccount(), $r->expectedVersion, $r->feeRatePpm, $r->feeFixedMinor, $r->providerVatPpm, $r->holdMinutes));

        return new Response(status: 204);
    }

    private function save(string $occurrenceId, ?string $typeId, SaveTicketTypeRequest $r): string
    {
        /** @var string $id */
        $id = $this->commands->dispatch(new SaveTicketTypeCommand(
            $this->account->requireAccount(),
            $occurrenceId,
            $typeId,
            $r->expectedVersion,
            $r->name,
            $r->description,
            $r->priceMinor,
            $r->quantityCap,
            $r->sortOrder,
            $r->active,
        ));

        return $id;
    }

    /** @param array<string, mixed> $body */
    private static function noStore(array $body): JsonResponse
    {
        $response = new JsonResponse($body);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
