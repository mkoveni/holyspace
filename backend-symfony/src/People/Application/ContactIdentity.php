<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Domain\ValueObject\ContactKind;

interface ContactIdentity
{
    /**
     * Takes a transaction-scoped lock on the contact value, so two simultaneous intakes of the same number or
     * address queue up, then returns the id of the person currently holding it (null when nobody does).
     */
    public function claim(ContactKind $kind, string $value): ?string;
}
