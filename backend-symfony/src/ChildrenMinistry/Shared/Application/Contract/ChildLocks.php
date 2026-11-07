<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Application\Contract;

/** Serialises everything that changes one child's records. Taken before any session lock. */
interface ChildLocks
{
    /** Creates the child's guard row if needed and locks it until the surrounding transaction ends. */
    public function lock(string $childId): void;
}
