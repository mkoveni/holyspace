<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetFormSubmission;

use App\Forms\Application\ReadModel\FormsReadStore;
use App\Forms\Domain\Model\FormSubmission;
use OutOfBoundsException;

final readonly class GetFormSubmissionHandler
{
    public function __construct(private FormsReadStore $records)
    {
    }

    public function __invoke(GetFormSubmissionQuery $message): FormSubmission
    {
        $id = $message->id;

        return $this->records->find('submissions', $id) ?? throw new OutOfBoundsException('Submission not found.');
    }
}
