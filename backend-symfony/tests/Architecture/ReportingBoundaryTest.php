<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Reporting shows dates, counts and names. It must never be able to reach restricted payloads (incident details and
 * history, confidential care text, pickup evidence, guardian authority or consent evidence), neither through types
 * nor through tables, so a new report cannot leak them without first changing this test.
 */
final class ReportingBoundaryTest extends TestCase
{
    /** Types in other modules that carry or load restricted payloads. */
    private const array FORBIDDEN_TYPES = [
        'Incident', 'IncidentDetails', 'IncidentEvent', 'IncidentRepository', 'IncidentReader', 'IncidentAccess',
        'IncidentAccessView', 'IncidentReceipt', 'SupportRecord', 'SupportRecordView', 'SupportReader',
        'SupportRepository', 'SafeguardingRepository', 'SafeguardingReader', 'Consent', 'GuardianAuthority',
        'PickupAuthorization', 'ConsentEvidence',
    ];

    /** Tables that hold restricted payloads. Reporting reads attendance, session and layout tables only. */
    private const array FORBIDDEN_TABLES = [
        'children_incidents', 'children_incident_details', 'children_incident_events', 'children_support_records',
        'children_pickup_authorizations', 'children_consents', 'children_consent_policies',
        'children_guardian_authorities', 'children_guardian_communications', 'children_verified_emails',
    ];

    /** Columns that hold secrets, which no report query may select. */
    private const array FORBIDDEN_COLUMNS = ['label_hash', 'pickup_code', 'evidence_reference', 'collector_id'];

    public function testReportingImportsNoRestrictedPayloadTypes(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/Reporting') as $path => $source) {
            foreach (self::FORBIDDEN_TYPES as $type) {
                if (1 === preg_match('/\buse App\\\\(?:ChildrenMinistry\\\\(?:Incidents|Safeguarding)|Communications)\\\\[A-Za-z\\\\]*\\\\'.$type.'\b/', $source)) {
                    $violations[] = "$path imports $type";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testReportingReachesIncidentsAndSafeguardingOnlyThroughTheirContracts(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/Reporting') as $path => $source) {
            preg_match_all('/^use App\\\\ChildrenMinistry\\\\(Incidents|Safeguarding)\\\\([A-Za-z\\\\]+);/m', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as [$statement, , $rest]) {
                if (!str_starts_with($rest, 'Application\\Contract\\')) {
                    $violations[] = "$path: $statement";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testReportingQueriesTouchNoRestrictedTablesOrSecretColumns(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/Reporting') as $path => $source) {
            foreach (self::FORBIDDEN_TABLES as $table) {
                if (1 === preg_match('/\b'.$table.'\b/', $source)) {
                    $violations[] = "$path names $table";
                }
            }
            foreach (self::FORBIDDEN_COLUMNS as $column) {
                if (1 === preg_match('/\b'.$column.'\b/', $source)) {
                    $violations[] = "$path names $column";
                }
            }
        }

        self::assertSame([], $violations);
    }
}
