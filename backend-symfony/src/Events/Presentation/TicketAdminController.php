<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\ConfigurePublicSale\ConfigurePublicSaleCommand;
use App\Events\Application\Command\ConfigureTicketFees\ConfigureTicketFeesCommand;
use App\Events\Application\Command\SaveTicketType\SaveTicketTypeCommand;
use App\Events\Application\Contract\TicketCatalog;
use App\Events\Application\TicketDoor;
use App\Events\Application\TicketInvoices;
use App\Events\Application\TicketResends;
use App\Events\Application\TicketVoids;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Presentation\Request\ConfigurePublicSaleRequest;
use App\Events\Presentation\Request\ConfigureTicketFeesRequest;
use App\Events\Presentation\Request\InvoiceSettingsRequest;
use App\Events\Presentation\Request\SaveTicketTypeRequest;
use App\Events\Presentation\Request\TicketScanRequest;
use App\Events\Presentation\Request\TicketVoidRequest;
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
        private TicketDoor $door,
        private TicketVoids $voids,
        private TicketResends $resends,
        private TicketInvoices $invoices,
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

    #[IsGranted('events.tickets.read')]
    #[Route('/api/events/occurrences/{id}/ticket-orders/{orderId}', methods: ['GET'])]
    public function order(#[PathUuid] string $id, #[PathUuid] string $orderId): JsonResponse
    {
        $order = $this->tickets->find($orderId);
        if (null === $order || $order->occurrenceId !== $id) {
            throw EventException::missing('Ticket order');
        }
        $contacts = $this->permissions->hasPermission('events.tickets.contacts');
        $documents = $this->invoices->documentsOf($orderId);

        return self::noStore([
            'id' => $order->id,
            'status' => $order->status,
            'seats' => $order->seats(),
            'subtotalMinor' => $order->subtotalMinor,
            'feeMinor' => $order->feeMinor,
            'totalMinor' => $order->totalMinor,
            'refundWaitingMinor' => $this->tickets->unmatchedRefund($orderId),
            'buyerName' => $contacts ? $order->buyerName : null,
            'buyerEmail' => $contacts ? $this->vault->decrypt($order->buyerEmailEncrypted) : null,
            'invoiceNumber' => $documents['number'] ?? null,
            'creditNotes' => array_map(static fn (array $n): array => ['number' => $n['number'], 'totalMinor' => $n['totalMinor']], $documents['creditNotes'] ?? []),
            'tickets' => array_map(static fn (array $t): array => [
                'id' => $t['id'], 'type' => $t['typeName'], 'priceMinor' => $t['unitPriceMinor'], 'holder' => $t['holder'], 'status' => $t['status'], 'credited' => null !== $t['creditNoteId'],
            ], $this->tickets->ticketPrices($orderId)),
        ]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/events/occurrences/{id}/ticket-orders/{orderId}/voids', methods: ['POST'])]
    public function voidTickets(#[PathUuid] string $id, #[PathUuid] string $orderId, #[MapRequestPayload] TicketVoidRequest $r): JsonResponse
    {
        return self::noStore($this->voids->void($this->account->requireAccount(), $id, $orderId, $r->ticketIds ?? []));
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/events/occurrences/{id}/ticket-orders/{orderId}/resend', methods: ['POST'])]
    public function resend(#[PathUuid] string $id, #[PathUuid] string $orderId): Response
    {
        $this->resends->byStaff($this->account->requireAccount(), $id, $orderId);

        return new Response(status: 204);
    }

    #[IsGranted('events.tickets.scan')]
    #[Route('/api/events/occurrences/{id}/tickets/scan', methods: ['POST'])]
    public function scan(#[PathUuid] string $id, #[MapRequestPayload] TicketScanRequest $r): JsonResponse
    {
        $s = $this->door->scan($this->account->requireAccount(), $id, (string) $r->code);

        return self::noStore([
            'result' => $s['result'],
            'reason' => $s['reason'],
            'ticketType' => $s['ticketType'],
            'holder' => $s['holder'],
            'checkedInAt' => null === $s['checkedInAt'] ? null : Json::instant($s['checkedInAt']),
            'counts' => $s['counts'],
        ]);
    }

    #[IsGranted('events.tickets.read')]
    #[Route('/api/events/occurrences/{id}/ticket-report', methods: ['GET'])]
    public function report(#[PathUuid] string $id): JsonResponse
    {
        return self::noStore($this->catalog->report($id) + ['scan' => $this->tickets->scanCounts($id)]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/invoice-settings', methods: ['GET'])]
    public function invoiceSettings(): JsonResponse
    {
        return self::noStore(['settings' => $this->invoices->settings($this->account->requireAccount())]);
    }

    #[IsGranted('events.tickets.manage')]
    #[Route('/api/invoice-settings', methods: ['PUT'])]
    public function changeInvoiceSettings(#[MapRequestPayload] InvoiceSettingsRequest $r): Response
    {
        $this->invoices->changeSettings($this->account->requireAccount(), $r->effectiveFrom, $r->legalName, $r->address, $r->registrationNumber, $r->npoNumber, $r->vatNumber, $r->vatRegistered, $r->vatRateBp);

        return new Response(status: 204);
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
