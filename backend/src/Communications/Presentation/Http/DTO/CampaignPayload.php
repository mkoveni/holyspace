<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CampaignPayload
{
    public function __construct(public string $name, public string $templateId, #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])] public array $recipientIds)
    {
    }
}
