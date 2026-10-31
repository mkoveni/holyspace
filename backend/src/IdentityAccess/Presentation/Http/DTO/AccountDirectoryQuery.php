<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccountDirectoryQuery
{
    public function __construct(#[Assert\Length(max: 100)] public string $search = '', #[Assert\Range(min: 1, max: 100)] public int $limit = 50)
    {
    }
}
