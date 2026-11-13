<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListForms;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\Contract\FormSummary;
use App\Forms\Application\FormAccess;

final readonly class ListFormsHandler
{
    public function __construct(private FormAccess $access, private FormReader $reader)
    {
    }

    /** @return list<FormSummary> */
    public function handle(ListFormsQuery $q): array
    {
        $this->access->read();

        return $this->reader->list(max(1, min(100, $q->limit)), max(0, $q->offset));
    }
}
