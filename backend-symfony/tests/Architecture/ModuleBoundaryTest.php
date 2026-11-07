<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Inside a modular context (Children Ministry) modules share Domain types and contracts, never each other's adapters or handlers. */
final class ModuleBoundaryTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function modules(): iterable
    {
        foreach (BoundaryRules::MODULAR_CONTEXTS as $context) {
            foreach (glob(dirname(__DIR__, 2).'/src/'.$context.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                yield $context.'\\'.basename($directory) => [$context, basename($directory)];
            }
        }
    }

    #[DataProvider('modules')]
    public function testModuleOnlyUsesSiblingDomainAndContracts(string $context, string $module): void
    {
        $violations = [];
        foreach (BoundaryRules::sources(dirname(__DIR__, 2).'/src/'.$context.'/'.$module) as $path => $source) {
            foreach (BoundaryRules::moduleViolations($context, $module, $source) as $statement) {
                $violations[] = "$path: $statement";
            }
        }

        self::assertSame([], $violations, "$context\\$module reaches into a sibling module's internals.");
    }
}
