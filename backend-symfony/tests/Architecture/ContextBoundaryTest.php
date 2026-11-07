<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Bounded contexts integrate only through each other's published Application\Contract interfaces
 * and the Shared kernel.
 */
final class ContextBoundaryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function contexts(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/src/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $context = basename($directory);
            if ('Shared' !== $context) {
                yield $context => [$context];
            }
        }
    }

    #[DataProvider('contexts')]
    public function testContextOnlyUsesOtherContextsThroughPublishedContracts(string $context): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/'.$context) as $path => $source) {
            foreach (BoundaryRules::contextViolations($context, $source) as $statement) {
                $violations[] = "$path: $statement";
            }
        }

        self::assertSame([], $violations, "$context reaches into another context's internals; depend on its Application\\Contract instead.");
    }
}
