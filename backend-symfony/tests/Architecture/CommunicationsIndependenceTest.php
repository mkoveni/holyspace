<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/** Communications delivers email for other contexts and knows none of them: they register policies with it, not the reverse. */
final class CommunicationsIndependenceTest extends TestCase
{
    public function testCommunicationsMentionsNoOtherBusinessContext(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/Communications') as $path => $source) {
            if (1 === preg_match('/\bApp\\\\(?:ChildrenMinistry|Volunteering|People)\\\\/', $source)) {
                $violations[] = $path;
            }
            if (1 === preg_match('/children[-_ ]ministry|children_|guardian/i', preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source) ?? '')) {
                $violations[] = "$path names a Children concept in code";
            }
        }

        self::assertSame([], $violations);
    }

    public function testCommunicationsReachesIdentityOnlyThroughItsPublishedContracts(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/Communications') as $path => $source) {
            foreach (BoundaryRules::contextViolations('Communications', $source) as $statement) {
                $violations[] = "$path: $statement";
            }
        }

        self::assertSame([], $violations);
    }
}
