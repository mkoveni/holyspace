<?php

declare(strict_types=1);

namespace App\Events\Application\Command\SelfCheckInVisitor;

use App\Events\Application\VisitorWelcome;
use App\Shared\Application\Command;

/** @implements Command<VisitorWelcome> */
final readonly class SelfCheckInVisitorCommand implements Command
{
    public function __construct(
        public string $token,
        public string $firstName,
        public string $lastName,
        public ?string $mobile,
        public ?string $email,
        public ?string $howHeard,
        public ?int $partyAdults,
        public bool $consent,
        public string $consentVersion,
        /** @var array<array-key, array{firstName: string, birthDate: string}> children named on the form, for Children's Ministry staff */
        public array $children = [],
    ) {
    }
}
