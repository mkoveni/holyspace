<?php

declare(strict_types=1);

namespace App\LifeEvents\Domain\Details;

final readonly class MarriageDetails implements LifeEventDetails
{
    public function __construct(
        public ?string $venue = null,
        public ?string $officiantName = null,
        public ?string $certificateReference = null,
    ) {
    }

    public function toArray(): array
    {
        return ['venue' => $this->venue, 'officiantName' => $this->officiantName, 'certificateReference' => $this->certificateReference];
    }

    public static function fromArray(array $data): self
    {
        return new self(self::nullable($data['venue'] ?? null), self::nullable($data['officiantName'] ?? null), self::nullable($data['certificateReference'] ?? null));
    }

    private static function nullable(mixed $v): ?string
    {
        if (null === $v) {
            return null;
        }
        $v = trim((string) $v);

        return '' === $v ? null : $v;
    }
}
