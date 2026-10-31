<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\ReadModel;

use App\Forms\Application\ReadModel\FormsReadStore;
use App\Forms\Domain\Model\ChurchForm;
use App\Forms\Domain\Repository\FormsRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(FormsReadStore::class)]
final readonly class RepositoryFormsReadStore implements FormsReadStore
{
    public function __construct(private FormsRepository $records)
    {
    }

    public function find(string $kind, string $id): ?WorkflowRecord
    {
        return $this->records->find($kind, $id);
    }

    public function publicForm(string $token): ?ChurchForm
    {
        return $this->records->publicForm($token);
    }

    public function bySubmissionKey(string $formId, string $key): ?WorkflowRecord
    {
        return $this->records->bySubmissionKey($formId, $key);
    }

    public function page(string $kind, ?string $formId, ?string $after, int $limit): array
    {
        return $this->records->page($kind, $formId, $after, $limit);
    }
}
