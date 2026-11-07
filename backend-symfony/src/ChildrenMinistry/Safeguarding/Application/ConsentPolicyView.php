<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

final readonly class ConsentPolicyView
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $label,
        public string $documentReference,
        public string $contentHash,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
