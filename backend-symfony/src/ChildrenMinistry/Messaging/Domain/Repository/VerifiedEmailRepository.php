<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain\Repository;

use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;
use DateTimeImmutable;

/** Write side of guardian email verifications. */
interface VerifiedEmailRepository
{
    public function verify(VerifiedGuardianEmail $email): void;

    public function revoke(string $id, DateTimeImmutable $at): void;
}
