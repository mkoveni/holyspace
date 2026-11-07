<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Application;

use App\IdentityAccess\Application\Contract\ResponsibleAccount;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;

final readonly class FakeOwners implements ResponsibleAccountDirectory
{
    /** @param list<string> $eligible */
    public function __construct(private array $eligible)
    {
    }

    public function eligible(string $accountId, string $permission): bool
    {
        return 'children.incidents.manage' === $permission && \in_array($accountId, $this->eligible, true);
    }

    public function search(string $permission, string $search): array
    {
        return array_map(static fn (string $id): ResponsibleAccount => new ResponsibleAccount($id, 'Owner '.$id), $this->eligible);
    }
}
