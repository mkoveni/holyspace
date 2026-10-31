<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Command\CancelMemberBooking;

use App\Shared\Application\CQRS\Command;

final readonly class CancelMemberBookingCommand implements Command
{
    public function __construct(public string $id, public int $version)
    {
    }
}
