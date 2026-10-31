<?php

declare(strict_types=1);

namespace App\People\Application\Port;

use App\People\Application\ReadModel\MemberPersonalRecords;

interface MemberContactWriter extends MemberPersonalRecords
{
    public function updateContact(string $personId, ?string $email, ?string $phone, string $expectedRevision): array;
}
