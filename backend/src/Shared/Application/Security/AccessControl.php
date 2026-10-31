<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

interface AccessControl
{
    public function isGranted(string $permission, mixed $subject = null): bool;
}
