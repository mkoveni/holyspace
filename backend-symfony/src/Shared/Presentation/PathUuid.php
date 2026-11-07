<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Attribute;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Marks a controller argument that comes from a `{placeholder}` of the route and must be a UUID.
 * A malformed value is a 400, and the argument arrives lower-cased.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class PathUuid extends ValueResolver
{
    public function __construct()
    {
        parent::__construct(PathUuidResolver::class);
    }
}
