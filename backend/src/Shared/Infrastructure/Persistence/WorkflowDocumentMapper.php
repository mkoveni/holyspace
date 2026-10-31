<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Model\WorkflowRecord;
use InvalidArgumentException;

/** Technical JSON mapping only; context mappers own the allowed aggregate classes. */
abstract class WorkflowDocumentMapper
{
    abstract protected function types(): array;

    public function kind(WorkflowRecord $record): string
    {
        $kind = array_search($record::class, $this->types(), true);

        return false === $kind ? throw new InvalidArgumentException('Unsupported aggregate.') : $kind;
    }

    public function hydrate(string $kind, string $payload): WorkflowRecord
    {
        $class = $this->types()[$kind] ?? throw new InvalidArgumentException('Unsupported aggregate type.');

        return new $class(...json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
    }

    public function payload(WorkflowRecord $record, int $version): string
    {
        return json_encode(array_replace(get_object_vars($record), ['version' => $version]), JSON_THROW_ON_ERROR);
    }
}
