<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Tests\Architecture\BoundaryRules;
use PHPUnit\Framework\TestCase;

/**
 * Giving is confidential: it reaches other contexts only through published contracts, needs nothing from the
 * ministry contexts, and no other context reads its tables (so no People surface can leak financial data).
 */
final class GivingIndependenceTest extends TestCase
{
    public function testGivingImportsOnlyPublishedContractsOfOtherContexts(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(self::src().'/Giving') as $path => $source) {
            foreach (BoundaryRules::contextViolations('Giving', $source) as $statement) {
                $violations[] = "$path: $statement";
            }
        }

        self::assertSame([], $violations);
    }

    public function testGivingNeverMentionsTheMinistryContexts(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(self::src().'/Giving') as $path => $source) {
            if (1 === preg_match('/\bApp\\\\(?:Events|Volunteering|ChildrenMinistry|Communications|Communities)\\\\/', $source)) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    public function testNoOtherContextTouchesGivingTables(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(self::src()) as $path => $source) {
            if (!str_starts_with($path, 'Giving/') && 1 === preg_match('/\bgiving_[a-z_]+/', $source)) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    private static function src(): string
    {
        return dirname(__DIR__, 3).'/src';
    }
}
