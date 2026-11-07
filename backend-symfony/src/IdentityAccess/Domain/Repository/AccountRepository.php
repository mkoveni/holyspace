<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\Account;

/** Loads and persists complete account aggregates for command use cases. */
interface AccountRepository
{
    /** Loads the aggregate holding a row lock until the surrounding transaction ends. */
    public function findForUpdate(string $id): ?Account;

    /** Stores a newly registered account. */
    public function add(Account $account): void;

    /** Saves a new revision. Throws a conflict when the stored version is not the previous one. */
    public function save(Account $account): void;
}
