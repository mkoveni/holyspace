<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketInvoiceRepository;
use App\Events\Domain\TicketOrder;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\IdGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Invoices and credit notes for paid ticket orders (design section 6). Documents are insert-only and are shown from the
 * stored snapshot, never recomputed. Free orders are not invoiced. Both methods run inside a transaction that already
 * holds the order lock, so the counter increment and the insert commit or roll back together.
 */
final readonly class TicketInvoices
{
    public function __construct(
        private TicketInvoiceRepository $invoices,
        private TicketCatalog $catalog,
        private PermissionChecker $permissions,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** Issues the invoice of a paid order once. Safe to call again. */
    public function issueFor(TicketOrder $order, DateTimeImmutable $at): void
    {
        if ($order->isFree() || null !== $this->invoices->invoiceOf($order->id)) {
            return;
        }
        $event = $this->catalog->event($order->occurrenceId) ?? throw EventException::missing('Event');
        $on = $this->day($at);
        $settings = $this->invoices->settingsOn($on);
        $when = $event['startsAt']->setTimezone(new DateTimeZone($event['timeZone']))->format('j M Y');
        $lines = [];
        foreach ($order->lines as $line) {
            $lines[] = ['description' => $event['title'].' · '.$line->typeName.' ('.$when.')', 'quantity' => $line->quantity, 'unitMinor' => $line->unitPriceMinor, 'lineMinor' => $line->quantity * $line->unitPriceMinor];
        }
        if ($order->feeMinor > 0) {
            $lines[] = ['description' => 'Card processing fee', 'quantity' => 1, 'unitMinor' => $order->feeMinor, 'lineMinor' => $order->feeMinor];
        }
        $rate = $settings['vatRegistered'] ? $settings['vatRateBp'] : 0;
        $this->invoices->insertInvoice([
            'id' => $this->ids->uuid(),
            'number' => $this->number('INV', $on),
            'title' => $settings['vatRegistered'] ? 'Tax Invoice' : 'Invoice',
            'orderId' => $order->id,
            'settingsId' => $settings['id'],
            'issuedOn' => $on,
            'buyerName' => $order->buyerName,
            'eventTitle' => $event['title'],
            'subtotalMinor' => $order->subtotalMinor,
            'feeMinor' => $order->feeMinor,
            'vatMinor' => self::vat($order->totalMinor, $rate),
            'totalMinor' => $order->totalMinor,
            'vatRateBp' => $rate,
            'lines' => $lines,
        ], $at);
    }

    /**
     * Credits `$amountMinor` against the order's invoice. `$ticketRows` are the tickets this refund covers (price per seat);
     * a full refund also returns the processing fee. When the lines do not add up to the amount, one plain line carries it.
     *
     * @param list<array{id: string, typeName: string, unitPriceMinor: int}> $ticketRows
     *
     * @return string|null the credit note id, or null when the order has no invoice (a free order)
     */
    public function creditFor(TicketOrder $order, int $amountMinor, bool $full, array $ticketRows, DateTimeImmutable $at): ?string
    {
        $invoice = $this->invoices->invoiceOf($order->id);
        if (null === $invoice || $amountMinor <= 0) {
            return null;
        }
        $lines = [];
        $sum = 0;
        $grouped = [];
        foreach ($ticketRows as $t) {
            $key = $t['typeName']."\0".$t['unitPriceMinor'];
            $grouped[$key] = ($grouped[$key] ?? ['type' => $t['typeName'], 'unit' => $t['unitPriceMinor'], 'n' => 0]);
            ++$grouped[$key]['n'];
        }
        foreach ($grouped as $g) {
            if ($g['unit'] > 0) {
                $lines[] = ['description' => $invoice['eventTitle'].' · '.$g['type'].' × '.$g['n'], 'lineMinor' => $g['n'] * $g['unit']];
                $sum += $g['n'] * $g['unit'];
            }
        }
        if ($full && $order->feeMinor > 0 && $sum + $order->feeMinor === $amountMinor) {
            $lines[] = ['description' => 'Card processing fee', 'lineMinor' => $order->feeMinor];
            $sum += $order->feeMinor;
        }
        if ($sum !== $amountMinor) {
            $lines = [['description' => 'Refund of '.$invoice['eventTitle'].' tickets', 'lineMinor' => $amountMinor]];
        }
        $on = $this->day($at);
        $id = $this->ids->uuid();
        $this->invoices->insertCreditNote([
            'id' => $id,
            'number' => $this->number('CN', $on),
            'invoiceId' => $invoice['id'],
            'issuedOn' => $on,
            'totalMinor' => $amountMinor,
            'vatMinor' => self::vat($amountMinor, $invoice['vatRateBp']),
            'lines' => $lines,
        ], $at);

        return $id;
    }

    /**
     * What a buyer sees of their paperwork, or null for a free order.
     *
     * @return array<string, mixed>|null
     */
    public function documentsOf(string $orderId): ?array
    {
        $invoice = $this->invoices->invoiceOf($orderId);
        if (null === $invoice) {
            return null;
        }
        $s = $invoice['settings'];

        return [
            'number' => $invoice['number'],
            'title' => $invoice['title'],
            'issuedOn' => $invoice['issuedOn'],
            'issuer' => ['legalName' => $s['legalName'], 'address' => $s['address'], 'registrationNumber' => $s['registrationNumber'], 'npoNumber' => $s['npoNumber'], 'vatNumber' => $s['vatRegistered'] ? $s['vatNumber'] : ''],
            'buyer' => ['name' => $invoice['buyerName'], 'address' => $invoice['buyerAddress'], 'vatNumber' => $invoice['buyerVatNumber']],
            'lines' => $invoice['lines'],
            'subtotalMinor' => $invoice['subtotalMinor'],
            'feeMinor' => $invoice['feeMinor'],
            'vatMinor' => $invoice['vatMinor'],
            'vatRateBp' => $invoice['vatRateBp'],
            'totalMinor' => $invoice['totalMinor'],
            'creditNotes' => $this->invoices->creditNotesOf($invoice['id']),
        ];
    }

    /** @return list<array<string, mixed>> newest first */
    public function settings(string $actorId): array
    {
        $this->permissions->requireActorPermission($actorId, 'events.tickets.manage');

        return $this->invoices->settingsHistory();
    }

    /** Adds a settings row effective from a date (never edits one), so documents already issued keep their details. */
    public function changeSettings(string $actorId, string $effectiveFrom, string $legalName, string $address, string $registrationNumber, string $npoNumber, string $vatNumber, bool $vatRegistered, int $vatRateBp): void
    {
        $this->permissions->requireActorPermission($actorId, 'events.tickets.manage');
        $history = $this->invoices->settingsHistory();
        $latest = $history[0] ?? null;
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom) || false === strtotime($effectiveFrom)) {
            throw EventException::invalid('Choose the date these details take effect.');
        }
        if (null !== $latest && $effectiveFrom <= $latest['effectiveFrom']) {
            throw EventException::conflict('The new details must take effect after the current ones ('.$latest['effectiveFrom'].'). Issued documents never change.');
        }
        if ('' === trim($legalName) || ($vatRegistered && '' === trim($vatNumber))) {
            throw EventException::invalid('Enter the church\'s legal name, and its VAT number when it is VAT registered.');
        }
        $this->invoices->saveSettings([
            'id' => $this->ids->uuid(), 'effectiveFrom' => $effectiveFrom, 'legalName' => trim($legalName), 'address' => trim($address), 'registrationNumber' => trim($registrationNumber),
            'npoNumber' => trim($npoNumber), 'vatNumber' => trim($vatNumber), 'vatRegistered' => $vatRegistered, 'vatRateBp' => $vatRateBp,
        ], $this->clock->now());
    }

    /** VAT contained in a VAT-inclusive amount: amount × rate / (100 + rate), rounded half up. */
    public static function vat(int $inclusiveMinor, int $rateBp): int
    {
        return 0 === $rateBp ? 0 : intdiv($inclusiveMinor * $rateBp + intdiv(10000 + $rateBp, 2), 10000 + $rateBp);
    }

    private function day(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone($this->calendar->timeZone()))->format('Y-m-d');
    }

    private function number(string $series, string $day): string
    {
        return \sprintf('%s-%s-%06d', $series, substr($day, 0, 4), $this->invoices->nextNumber($series));
    }
}
