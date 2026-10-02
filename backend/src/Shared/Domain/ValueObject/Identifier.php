<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

abstract readonly class Identifier
{
    final protected function __construct(
        private Uuid $value,
    ) {
    }

    final public static function generateValue(): Uuid
    {
        return Uuid::v7();
    }

    final public function toString(): string
    {
        return $this->value->toRfc4122();
    }

    final public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    final public function value(): Uuid
    {
        return $this->value;
    }
}
