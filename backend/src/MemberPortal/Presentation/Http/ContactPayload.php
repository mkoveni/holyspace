<?php

declare(strict_types=1);

namespace App\MemberPortal\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactPayload
{
    public function __construct(#[Assert\Email] #[Assert\Length(max: 254)] public ?string $email, #[Assert\Length(max: 40)] public ?string $phone, #[Assert\Regex('/^[a-f0-9]{64}$/D')] public string $expectedRevision)
    {
    }
}
