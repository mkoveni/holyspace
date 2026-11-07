<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\People\Domain\ValueObject\ContactKind;
use DateTimeImmutable;

final readonly class ContactMethod
{
    public string $value;

    private function __construct(
        public string $id,
        public string $personId,
        public ContactKind $kind,
        string $value,
        public bool $primary,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $endedAt,
    ) {
        $text = PeopleRules::text($value, 'Contact value', 254);
        if (ContactKind::Email === $kind) {
            $text = mb_strtolower($text);
            if (1 !== preg_match('/\A[^\s@]+@[^\s@]+\.[^\s@]+\z/u', $text)) {
                throw PeopleException::invalid('Invalid email address.');
            }
        } elseif (1 !== preg_match('/\A[+0-9() .-]{3,40}\z/', $text)) {
            throw PeopleException::invalid('Invalid phone number.');
        }
        $this->value = $text;
    }

    public static function add(string $id, string $personId, ContactKind $kind, string $value, bool $primary, DateTimeImmutable $now): self
    {
        return new self($id, $personId, $kind, $value, $primary, $now, null);
    }

    public static function reconstitute(
        string $id,
        string $personId,
        ContactKind $kind,
        string $value,
        bool $primary,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $endedAt,
    ): self {
        return new self($id, $personId, $kind, $value, $primary, $createdAt, $endedAt);
    }
}
