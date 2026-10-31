<?php

declare(strict_types=1);

namespace App\Shared\Domain\Model;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

abstract class WorkflowRecord
{
    public readonly string $id;
    public readonly string $createdAt;
    public readonly string $updatedAt;

    public function __construct(
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        public readonly int $version = 0,
    ) {
        $this->id = '' === $id ? Uuid::v7()->toRfc4122() : Uuid::fromString($id)->toRfc4122();
        $this->createdAt = '' === $createdAt ? Clock::get()->now()->format(DATE_ATOM) : $createdAt;
        $this->updatedAt = '' === $updatedAt ? $this->createdAt : $updatedAt;
    }

    protected function changed(array $changes): static
    {
        return new static(...array_replace(get_object_vars($this), $changes, ['updatedAt' => Clock::get()->now()->format(DATE_ATOM)]));
    }
}
