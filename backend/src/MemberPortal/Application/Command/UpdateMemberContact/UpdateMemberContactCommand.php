<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Command\UpdateMemberContact;

use App\Shared\Application\CQRS\Command;

final readonly class UpdateMemberContactCommand implements Command
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public string $revision,
    ) {
    }
}
