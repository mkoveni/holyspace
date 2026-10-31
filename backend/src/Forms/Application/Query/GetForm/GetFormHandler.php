<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetForm;

use App\Forms\Application\ReadModel\FormsReadStore;
use App\Forms\Domain\Model\ChurchForm;
use OutOfBoundsException;

final readonly class GetFormHandler
{
    public function __construct(private FormsReadStore $records)
    {
    }

    public function __invoke(GetFormQuery $message): ChurchForm
    {
        $id = $message->id;

        return $this->records->find('forms', $id) ?? throw new OutOfBoundsException('Form not found.');
    }
}
