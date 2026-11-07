<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain\Repository;

use App\ChildrenMinistry\Messaging\Domain\GuardianCommunication;

/** Write side of guardian communications. */
interface GuardianCommunicationRepository
{
    public function lock(string $id): ?GuardianCommunication;

    public function insert(GuardianCommunication $communication, string $requestId, string $requestHash): void;

    /** Updates the row only if its stored version is one less than the communication's; otherwise a 409. */
    public function update(GuardianCommunication $communication): void;
}
