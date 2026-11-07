<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeliveryReceiptRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $deliveryId = null,
        #[Assert\NotBlank]
        public ?string $messageId = null,
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public ?string $eventKey = null,
        #[Assert\NotBlank]
        public ?string $status = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $reference = null,
    ) {
    }
}
