<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

/** A catalogue entry such as a screening or a first-aid certificate. Codes are unique, lowercase and stable. */
final readonly class CredentialType
{
    public string $code;

    public string $name;

    private function __construct(public string $id, ?string $code, ?string $name)
    {
        $this->code = ServingRules::text($code, 80);
        if (1 !== preg_match('/\A[a-z][a-z0-9.-]{1,79}\z/', $this->code)) {
            throw VolunteerException::invalid('Use a lowercase credential code.');
        }
        $this->name = ServingRules::text($name, 150);
    }

    public static function create(string $id, ?string $code, ?string $name): self
    {
        return new self($id, $code, $name);
    }
}
