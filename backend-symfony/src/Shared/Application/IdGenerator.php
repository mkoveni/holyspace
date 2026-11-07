<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface IdGenerator
{
    /** @return string a new random UUID in RFC 4122 form */
    public function uuid(): string;
}
