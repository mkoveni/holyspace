<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Exception;
final class LifeEventNotFound extends \RuntimeException
{
    public static function withId(string $id): self { return new self(sprintf('Life event "%s" was not found.', $id)); }
}
