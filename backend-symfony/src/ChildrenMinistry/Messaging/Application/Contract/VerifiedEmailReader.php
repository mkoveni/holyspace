<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Contract;

use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;

interface VerifiedEmailReader
{
    /** @return list<VerifiedGuardianEmail> newest verification first, at most 100 */
    public function emails(string $guardianId): array;

    public function verification(string $id): ?VerifiedGuardianEmail;
}
