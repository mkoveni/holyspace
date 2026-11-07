<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Tests\Architecture\BoundaryRules;
use PHPUnit\Framework\TestCase;

/** Events reaches other contexts only through published contracts and never touches Children Ministry data. */
final class EventsIndependenceTest extends TestCase
{
    public function testEventsImportsOnlyPublishedContractsOfOtherContexts(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 3).'/src/Events') as $path => $source) {
            foreach (BoundaryRules::contextViolations('Events', $source) as $statement) {
                $violations[] = "$path: $statement";
            }
        }

        self::assertSame([], $violations);
    }

    public function testEventsNeverMentionsChildrenMinistry(): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 3).'/src/Events') as $path => $source) {
            if (1 === preg_match('/\bApp\\\\ChildrenMinistry\\\\/', $source)) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }
}
