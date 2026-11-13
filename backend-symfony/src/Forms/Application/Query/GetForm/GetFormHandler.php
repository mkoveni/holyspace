<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetForm;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\Contract\FormView;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;

final readonly class GetFormHandler
{
    public function __construct(private FormAccess $access, private FormReader $reader)
    {
    }

    public function handle(GetFormQuery $q): FormView
    {
        $this->access->read();

        return $this->reader->find($q->id) ?? throw FormException::missing();
    }
}
