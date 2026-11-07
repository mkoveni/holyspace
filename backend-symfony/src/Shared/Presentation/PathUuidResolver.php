<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[AsTargetedValueResolver]
final readonly class PathUuidResolver implements ValueResolverInterface
{
    /** @return iterable<string> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $value = $request->attributes->get($argument->getName());

        return [Json::uuid(is_string($value) ? $value : '')];
    }
}
