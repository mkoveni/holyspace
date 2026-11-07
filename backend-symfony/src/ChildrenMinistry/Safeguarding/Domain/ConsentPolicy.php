<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A published version of a programme's consent document, pinned by its SHA-256 hash. */
final readonly class ConsentPolicy
{
    public string $label;
    public string $documentReference;

    private function __construct(
        public string $id,
        public string $programmeId,
        ?string $label,
        ?string $documentReference,
        public ?string $contentHash,
        public DateTimeImmutable $createdAt,
    ) {
        $this->label = ChildrenRules::text($label, 40);
        $this->documentReference = ChildrenRules::text($documentReference, 255);
        if (null === $contentHash || 1 !== preg_match('/\A[a-f0-9]{64}\z/', $contentHash)) {
            throw ChildrenException::invalid('Supply the policy document SHA-256 hash.');
        }
    }

    public static function publish(string $id, string $programmeId, ?string $label, ?string $documentReference, ?string $contentHash, DateTimeImmutable $now): self
    {
        return new self($id, $programmeId, $label, $documentReference, $contentHash, $now);
    }

    public static function reconstitute(string $id, string $programmeId, string $label, string $documentReference, string $contentHash, DateTimeImmutable $createdAt): self
    {
        return new self($id, $programmeId, $label, $documentReference, $contentHash, $createdAt);
    }
}
