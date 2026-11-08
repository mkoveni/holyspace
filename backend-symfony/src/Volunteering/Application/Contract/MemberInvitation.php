<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `MemberVolunteerViews.Invitation`. */
final readonly class MemberInvitation
{
    public function __construct(public string $id, public int $version, public string $status, public DateTimeImmutable $expiresAt)
    {
    }
}
