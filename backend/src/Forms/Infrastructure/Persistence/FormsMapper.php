<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Persistence;

use App\Forms\Domain\Model\ChurchForm;
use App\Forms\Domain\Model\FormSubmission;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class FormsMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['forms' => ChurchForm::class, 'submissions' => FormSubmission::class];
    }
}
