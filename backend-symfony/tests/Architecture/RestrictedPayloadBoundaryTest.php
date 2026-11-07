<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Confidential support payloads are reachable only through Safeguarding's own audited use cases. No other module
 * (Reporting included) may name the support types, so a new read path cannot appear without touching this module.
 */
final class RestrictedPayloadBoundaryTest extends TestCase
{
    private const array RESTRICTED = ['SupportRecord', 'SupportRecordView', 'SupportReader', 'SupportRepository'];

    public function testNothingOutsideSafeguardingNamesTheSupportTypes(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2).'/src';
        foreach (BoundaryRules::sources($root) as $path => $source) {
            if (str_starts_with($path, 'ChildrenMinistry/Safeguarding/')) {
                continue;
            }
            foreach (self::RESTRICTED as $type) {
                if (1 === preg_match('/\bApp\\\\ChildrenMinistry\\\\Safeguarding\\\\[A-Za-z\\\\]*\\\\'.$type.'\b/', $source)) {
                    $violations[] = "$path imports $type";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testOnlyTheAuditedAccessHandlerReadsSupportRecords(): void
    {
        $readers = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/Safeguarding/Application') as $path => $source) {
            if (str_contains($source, '->supportRecord(') && !str_contains($path, 'Contract/')) {
                $readers[] = $path;
            }
        }

        self::assertSame(['Command/AccessChildSupport/AccessChildSupportHandler.php'], $readers);
    }
}
