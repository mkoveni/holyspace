<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

/** The two things about a programme a guardian message depends on. */
final readonly class ProgrammeFacts
{
    public function __construct(public string $name, public ?string $currentPolicyId)
    {
    }
}
