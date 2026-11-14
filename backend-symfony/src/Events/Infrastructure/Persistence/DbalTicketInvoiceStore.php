<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Domain\Repository\TicketInvoiceRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use LogicException;

final readonly class DbalTicketInvoiceStore implements TicketInvoiceRepository
{
    private const string SETTINGS = 'id, effective_from, legal_name, address, registration_number, npo_number, vat_number, vat_registered, vat_rate_bp';

    public function __construct(private Connection $connection)
    {
    }

    public function settingsOn(string $date): array
    {
        $r = $this->connection->fetchAssociative('SELECT '.self::SETTINGS.' FROM event_invoice_settings WHERE effective_from <= ? ORDER BY effective_from DESC LIMIT 1', [$date]);

        return self::settings($r ?: $this->connection->fetchAssociative('SELECT '.self::SETTINGS.' FROM event_invoice_settings ORDER BY effective_from LIMIT 1'));
    }

    public function settingsHistory(): array
    {
        return array_map(self::settings(...), $this->connection->fetchAllAssociative('SELECT '.self::SETTINGS.' FROM event_invoice_settings ORDER BY effective_from DESC'));
    }

    public function saveSettings(array $s, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_invoice_settings ('.self::SETTINGS.', created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$s['id'], $s['effectiveFrom'], $s['legalName'], $s['address'], $s['registrationNumber'], $s['npoNumber'], $s['vatNumber'], $s['vatRegistered'], $s['vatRateBp'], Timestamps::toDatabase($at)],
            [7 => ParameterType::BOOLEAN],
        );
    }

    public function nextNumber(string $series): int
    {
        return (int) $this->connection->fetchOne('UPDATE event_invoice_counters SET last_number = last_number + 1 WHERE series = ? RETURNING last_number', [$series]);
    }

    public function insertInvoice(array $i, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_invoices (id, number, title, ticket_order_id, settings_id, issued_on, buyer_name, event_title, subtotal_minor, fee_minor, vat_minor, total_minor, vat_rate_bp, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$i['id'], $i['number'], $i['title'], $i['orderId'], $i['settingsId'], $i['issuedOn'], $i['buyerName'], $i['eventTitle'], $i['subtotalMinor'], $i['feeMinor'], $i['vatMinor'], $i['totalMinor'], $i['vatRateBp'], Timestamps::toDatabase($at)],
        );
        foreach ($i['lines'] as $position => $line) {
            $this->connection->executeStatement(
                'INSERT INTO event_invoice_lines (id, invoice_id, position, description, quantity, unit_minor, line_minor) VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?)',
                [$i['id'], $position, $line['description'], $line['quantity'], $line['unitMinor'], $line['lineMinor']],
            );
        }
    }

    public function invoiceOf(string $orderId): ?array
    {
        $r = $this->connection->fetchAssociative(
            'SELECT i.id, i.number, i.title, i.issued_on, i.buyer_name, i.buyer_address, i.buyer_vat_number, i.event_title, i.subtotal_minor, i.fee_minor, i.vat_minor, i.total_minor, i.vat_rate_bp, i.settings_id
             FROM event_invoices i WHERE i.ticket_order_id = ?',
            [$orderId],
        );
        if (false === $r) {
            return null;
        }
        $settings = self::settings($this->connection->fetchAssociative('SELECT '.self::SETTINGS.' FROM event_invoice_settings WHERE id = ?', [$r['settings_id']]));
        $lines = $this->connection->fetchAllAssociative('SELECT description, quantity, unit_minor, line_minor FROM event_invoice_lines WHERE invoice_id = ? ORDER BY position', [$r['id']]);

        return [
            'id' => (string) $r['id'], 'number' => (string) $r['number'], 'title' => (string) $r['title'], 'issuedOn' => (string) $r['issued_on'], 'buyerName' => (string) $r['buyer_name'],
            'buyerAddress' => (string) $r['buyer_address'], 'buyerVatNumber' => (string) $r['buyer_vat_number'], 'eventTitle' => (string) $r['event_title'],
            'subtotalMinor' => (int) $r['subtotal_minor'], 'feeMinor' => (int) $r['fee_minor'], 'vatMinor' => (int) $r['vat_minor'], 'totalMinor' => (int) $r['total_minor'], 'vatRateBp' => (int) $r['vat_rate_bp'],
            'settings' => $settings,
            'lines' => array_map(static fn (array $l): array => ['description' => (string) $l['description'], 'quantity' => (int) $l['quantity'], 'unitMinor' => (int) $l['unit_minor'], 'lineMinor' => (int) $l['line_minor']], $lines),
        ];
    }

    public function insertCreditNote(array $n, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_credit_notes (id, number, invoice_id, issued_on, total_minor, vat_minor, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$n['id'], $n['number'], $n['invoiceId'], $n['issuedOn'], $n['totalMinor'], $n['vatMinor'], Timestamps::toDatabase($at)],
        );
        foreach ($n['lines'] as $position => $line) {
            $this->connection->executeStatement(
                'INSERT INTO event_credit_note_lines (id, credit_note_id, position, description, line_minor) VALUES (gen_random_uuid(), ?, ?, ?, ?)',
                [$n['id'], $position, $line['description'], $line['lineMinor']],
            );
        }
    }

    public function creditNotesOf(string $invoiceId): array
    {
        $notes = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, number, issued_on, total_minor, vat_minor FROM event_credit_notes WHERE invoice_id = ? ORDER BY created_at, number', [$invoiceId]) as $r) {
            $lines = $this->connection->fetchAllAssociative('SELECT description, line_minor FROM event_credit_note_lines WHERE credit_note_id = ? ORDER BY position', [$r['id']]);
            $notes[] = [
                'id' => (string) $r['id'], 'number' => (string) $r['number'], 'issuedOn' => (string) $r['issued_on'], 'totalMinor' => (int) $r['total_minor'], 'vatMinor' => (int) $r['vat_minor'],
                'lines' => array_map(static fn (array $l): array => ['description' => (string) $l['description'], 'lineMinor' => (int) $l['line_minor']], $lines),
            ];
        }

        return $notes;
    }

    /**
     * @param array<string, mixed>|false $r
     *
     * @return array{id: string, effectiveFrom: string, legalName: string, address: string, registrationNumber: string, npoNumber: string, vatNumber: string, vatRegistered: bool, vatRateBp: int}
     */
    private static function settings(array|false $r): array
    {
        if (false === $r) {
            throw new LogicException('Invoice settings are missing.');
        }

        return [
            'id' => (string) $r['id'], 'effectiveFrom' => (string) $r['effective_from'], 'legalName' => (string) $r['legal_name'], 'address' => (string) $r['address'],
            'registrationNumber' => (string) $r['registration_number'], 'npoNumber' => (string) $r['npo_number'], 'vatNumber' => (string) $r['vat_number'],
            'vatRegistered' => (bool) $r['vat_registered'], 'vatRateBp' => (int) $r['vat_rate_bp'],
        ];
    }
}
