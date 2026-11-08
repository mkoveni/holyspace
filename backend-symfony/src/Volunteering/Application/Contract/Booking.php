<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Application\Readiness;

/** Java `ServingViews.Booking`: an assignment with its volunteer's name, readiness and invitations. */
final readonly class Booking
{
    /** @param list<InvitationDetails> $invitations */
    public function __construct(public AssignmentView $assignment, public string $name, public Readiness $readiness, public array $invitations)
    {
    }
}
