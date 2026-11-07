<?php

declare(strict_types=1);

namespace App\People\Domain;

use DateTimeImmutable;

final readonly class PersonAddress
{
    public string $line1;
    public string $line2;
    public string $city;
    public string $region;
    public string $postalCode;
    public string $countryCode;

    private function __construct(
        public string $id,
        public string $personId,
        string $line1,
        ?string $line2,
        string $city,
        ?string $region,
        ?string $postalCode,
        string $countryCode,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $endedAt,
    ) {
        $this->line1 = PeopleRules::text($line1, 'Address line', 200);
        $this->line2 = PeopleRules::optionalText($line2, 'Address line 2', 200);
        $this->city = PeopleRules::text($city, 'City', 100);
        $this->region = PeopleRules::optionalText($region, 'Region', 100);
        $this->postalCode = PeopleRules::optionalText($postalCode, 'Postal code', 20);
        $country = mb_strtoupper(PeopleRules::text($countryCode, 'Country code', 2));
        if (1 !== preg_match('/\A[A-Z]{2}\z/', $country)) {
            throw PeopleException::invalid('Use a two-letter country code.');
        }
        $this->countryCode = $country;
    }

    public static function add(
        string $id,
        string $personId,
        string $line1,
        ?string $line2,
        string $city,
        ?string $region,
        ?string $postalCode,
        string $countryCode,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $personId, $line1, $line2, $city, $region, $postalCode, $countryCode, $now, null);
    }

    public static function reconstitute(
        string $id,
        string $personId,
        string $line1,
        ?string $line2,
        string $city,
        ?string $region,
        ?string $postalCode,
        string $countryCode,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $endedAt,
    ): self {
        return new self($id, $personId, $line1, $line2, $city, $region, $postalCode, $countryCode, $createdAt, $endedAt);
    }
}
