<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/** A verified credential of a volunteer. The evidence note is restricted: only the credential's own row holds it. */
final readonly class Credential
{
    public string $evidence;

    private function __construct(
        public string $id,
        public string $volunteerId,
        public string $typeId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        ?string $evidence,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt,
    ) {
        ServingRules::period($validFrom, $validUntil);
        $this->evidence = ServingRules::text($evidence, 500);
    }

    public static function record(string $id, string $volunteerId, string $typeId, ?DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil, ?string $evidence, string $verifiedBy, DateTimeImmutable $verifiedAt): self
    {
        return new self($id, $volunteerId, $typeId, ServingRules::instant($validFrom), ServingRules::instant($validUntil), $evidence, $verifiedBy, $verifiedAt);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}
