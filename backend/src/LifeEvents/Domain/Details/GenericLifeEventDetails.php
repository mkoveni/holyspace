<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Details;
final readonly class GenericLifeEventDetails implements LifeEventDetails
{
    /** @param array<string,mixed> $attributes */
    public function __construct(private array $attributes=[]) {}
    public function toArray(): array { return $this->attributes; }
}
