<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TicketVoidRequest
{
    /** @param list<string>|null $ticketIds */
    public function __construct(
        #[Assert\NotBlank] #[Assert\Count(min: 1, max: 50)] #[Assert\All([new Assert\Uuid()])] public ?array $ticketIds = null,
    ) {
    }
}
