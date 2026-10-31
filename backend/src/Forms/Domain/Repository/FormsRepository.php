<?php

declare(strict_types=1);

namespace App\Forms\Domain\Repository;

use App\Forms\Domain\Model\ChurchForm;
use App\Shared\Domain\Model\WorkflowRecord;

interface FormsRepository
{
    public function save(WorkflowRecord $record): void;

    public function find(string $kind, string $id): ?WorkflowRecord;

    public function publicForm(string $token): ?ChurchForm;

    public function bySubmissionKey(string $formId, string $key): ?WorkflowRecord;

    public function page(string $kind, ?string $formId, ?string $after, int $limit): array;
}
