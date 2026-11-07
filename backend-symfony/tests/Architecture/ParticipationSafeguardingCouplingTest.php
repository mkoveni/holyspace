<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Participation and Safeguarding reference each other (Java's cycle). The port keeps the coupling narrow and
 * explicit: Participation sees Safeguarding only through published contracts, and Safeguarding sees Participation
 * through its read contracts plus the programme aggregate it must update when a consent policy is published.
 */
final class ParticipationSafeguardingCouplingTest extends TestCase
{
    private const array PARTICIPATION_TYPES_SAFEGUARDING_MAY_USE = [
        'Domain\\Programme',
        'Domain\\Repository\\ParticipationRepository',
    ];

    public function testParticipationUsesSafeguardingOnlyThroughContracts(): void
    {
        $violations = [];
        foreach ($this->imports('Participation', 'Safeguarding') as $path => $statements) {
            foreach ($statements as $import) {
                if (!str_starts_with($import, 'Application\\Contract\\')) {
                    $violations[] = "$path imports Safeguarding\\$import";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testSafeguardingUsesOnlyParticipationReadContractsAndTheProgrammeWriteSide(): void
    {
        $violations = [];
        foreach ($this->imports('Safeguarding', 'Participation') as $path => $statements) {
            foreach ($statements as $import) {
                if (!str_starts_with($import, 'Application\\Contract\\') && !in_array($import, self::PARTICIPATION_TYPES_SAFEGUARDING_MAY_USE, true)) {
                    $violations[] = "$path imports Participation\\$import";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testNoStandInsRemainInTheTwoModules(): void
    {
        $placeholders = [];
        foreach (['Participation', 'Safeguarding'] as $module) {
            foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/'.$module) as $path => $source) {
                if (1 === preg_match('/\bPLACEHOLDER\b|\bclass Pending/', $source)) {
                    $placeholders[] = "$module/$path";
                }
            }
        }

        self::assertSame([], $placeholders, 'Sessions implements the session-scoped support check; nothing may stand in for a missing collaborator');
    }

    /** @return array<string, list<string>> file => the other module's types it imports, relative to that module */
    private function imports(string $module, string $other): array
    {
        $found = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/ChildrenMinistry/'.$module) as $path => $source) {
            preg_match_all('/^use\s+App\\\\ChildrenMinistry\\\\'.$other.'\\\\([^;\s]+)/m', $source, $matches);
            if ([] !== $matches[1]) {
                $found["$module/$path"] = $matches[1];
            }
        }

        return $found;
    }
}
