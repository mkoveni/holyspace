<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Details;
interface LifeEventDetails
{
    /** @return array<string,mixed> */
    public function toArray(): array;
}
