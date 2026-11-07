<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

final readonly class CreateLocationRequest
{
    public function __construct(
        public string $name = '',
        public string $timeZone = '',
    ) {
    }
}
