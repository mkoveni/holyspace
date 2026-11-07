<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

final readonly class TitleRequest
{
    public function __construct(
        public string $code = '',
        public string $label = '',
    ) {
    }
}
