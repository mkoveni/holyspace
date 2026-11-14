<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use DateTimeImmutable;

/**
 * Insert-only invoices and credit notes (design section 6). Numbers come from a counter row incremented in the caller's
 * transaction, so a rollback gives the number back and a series has no gaps.
 *
 * @phpstan-type Settings array{id: string, effectiveFrom: string, legalName: string, address: string, registrationNumber: string, npoNumber: string, vatNumber: string, vatRegistered: bool, vatRateBp: int}
 * @phpstan-type Line array{description: string, quantity: int, unitMinor: int, lineMinor: int}
 */
interface TicketInvoiceRepository
{
    /**
     * The settings row in force on a date (`Y-m-d`): the newest one whose effective date has arrived.
     *
     * @return Settings
     */
    public function settingsOn(string $date): array;

    /** @return list<Settings> newest first */
    public function settingsHistory(): array;

    /** @param Settings $settings */
    public function saveSettings(array $settings, DateTimeImmutable $at): void;

    /** Moves the series counter and returns the new number. Call inside the transaction that inserts the document. */
    public function nextNumber(string $series): int;

    /**
     * @param array{id: string, number: string, title: string, orderId: string, settingsId: string, issuedOn: string, buyerName: string, eventTitle: string, subtotalMinor: int, feeMinor: int, vatMinor: int, totalMinor: int, vatRateBp: int, lines: list<Line>} $invoice
     */
    public function insertInvoice(array $invoice, DateTimeImmutable $at): void;

    /**
     * @return array{id: string, number: string, title: string, issuedOn: string, buyerName: string, buyerAddress: string, buyerVatNumber: string, eventTitle: string, subtotalMinor: int, feeMinor: int, vatMinor: int, totalMinor: int, vatRateBp: int, settings: Settings, lines: list<Line>}|null
     */
    public function invoiceOf(string $orderId): ?array;

    /**
     * @param array{id: string, number: string, invoiceId: string, issuedOn: string, totalMinor: int, vatMinor: int, lines: list<array{description: string, lineMinor: int}>} $note
     */
    public function insertCreditNote(array $note, DateTimeImmutable $at): void;

    /** @return list<array{id: string, number: string, issuedOn: string, totalMinor: int, vatMinor: int, lines: list<array{description: string, lineMinor: int}>}> oldest first */
    public function creditNotesOf(string $invoiceId): array;
}
