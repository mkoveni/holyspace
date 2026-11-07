<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The context and module walls, as pure functions over source text so the rules themselves can be tested.
 * Deptrac enforces the layers inside a context; this enforces who may import whom across contexts and modules.
 */
final class BoundaryRules
{
    /** Contexts split into modules: src/<Context>/<Module>/<Layer>. */
    public const array MODULAR_CONTEXTS = ['ChildrenMinistry'];

    private function __construct()
    {
    }

    /**
     * Another context may be used only through its published contracts, at context or module level
     * (App\Other\Application\Contract\X or App\Other\Module\Application\Contract\X). Shared is the kernel.
     *
     * @return list<string> the offending import statements
     */
    public static function contextViolations(string $context, string $source): array
    {
        $violations = [];
        foreach (self::imports($source) as [$statement, $other, $path]) {
            if (!in_array($other, [$context, 'Shared'], true) && 1 !== preg_match('/\A(?:[A-Za-z]+\\\\)?Application\\\\Contract\\\\/', $path)) {
                $violations[] = $statement;
            }
        }

        return $violations;
    }

    /**
     * Inside a modular context a module may use another module's Domain and published Application\Contract
     * types, never its infrastructure, presentation or handlers. The module named Shared is the kernel and
     * depends on no other module.
     *
     * @return list<string> the offending import statements
     */
    public static function moduleViolations(string $context, string $module, string $source): array
    {
        $violations = [];
        foreach (self::imports($source) as [$statement, $other, $path]) {
            if ($other !== $context || 1 !== preg_match('/\A([A-Za-z]+)\\\\(.+)\z/', $path, $parts)) {
                continue;
            }
            [, $targetModule, $rest] = $parts;
            if ($targetModule === $module || 'Shared' === $targetModule) {
                continue;
            }
            $published = str_starts_with($rest, 'Domain\\') || str_starts_with($rest, 'Application\\Contract\\');
            if ('Shared' === $module || !$published) {
                $violations[] = $statement;
            }
        }

        return $violations;
    }

    /** @return iterable<string, string> relative path => file contents, for every PHP file below the directory */
    public static function sources(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ('php' === $file->getExtension()) {
                yield substr($file->getPathname(), strlen($directory) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }

    /** @return list<array{string, string, string}> statement, first namespace segment, remaining path */
    private static function imports(string $source): array
    {
        preg_match_all('/^use\s+App\\\\([A-Za-z]+)\\\\([^;\s]+)/m', $source, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [trim($match[0]), $match[1], $match[2]], $matches);
    }
}
