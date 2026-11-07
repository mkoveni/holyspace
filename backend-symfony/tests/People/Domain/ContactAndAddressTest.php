<?php

declare(strict_types=1);

namespace App\Tests\People\Domain;

use App\People\Domain\ContactMethod;
use App\People\Domain\PeopleException;
use App\People\Domain\PersonAddress;
use App\People\Domain\ValueObject\ContactKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactAndAddressTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    public function testEmailsAreLowerCasedAndTrimmed(): void
    {
        self::assertSame('family@church.org', $this->contact(ContactKind::Email, '  Family@Church.org ')->value);
    }

    /** @return iterable<string, array{ContactKind, string}> */
    public static function invalidContacts(): iterable
    {
        yield 'email without domain' => [ContactKind::Email, 'someone@'];
        yield 'email with space' => [ContactKind::Email, 'bad address'];
        yield 'blank email' => [ContactKind::Email, '   '];
        yield 'phone with letters' => [ContactKind::Phone, '0123abc'];
        yield 'phone too short' => [ContactKind::Phone, '12'];
        yield 'email too long' => [ContactKind::Email, 'a@'.str_repeat('b', 260).'.org'];
    }

    #[DataProvider('invalidContacts')]
    public function testInvalidContactsAreRejected(ContactKind $kind, string $value): void
    {
        $this->expectException(PeopleException::class);

        $this->contact($kind, $value);
    }

    public function testPhoneNumbersKeepTheirFormatting(): void
    {
        self::assertSame('+27 (82) 123-4567', $this->contact(ContactKind::Phone, '+27 (82) 123-4567')->value);
    }

    public function testAddressIsNormalised(): void
    {
        $address = PersonAddress::add(self::ID, self::ID, ' 1 Church St ', null, ' Pretoria ', null, ' 0001 ', 'za', new DateTimeImmutable());

        self::assertSame('1 Church St', $address->line1);
        self::assertSame('', $address->line2);
        self::assertSame('Pretoria', $address->city);
        self::assertSame('', $address->region);
        self::assertSame('0001', $address->postalCode);
        self::assertSame('ZA', $address->countryCode);
    }

    public function testAddressNeedsLineCityAndATwoLetterCountry(): void
    {
        foreach ([['', 'City', 'ZA'], ['Line', '', 'ZA'], ['Line', 'City', 'ZAF'], ['Line', 'City', '1A']] as [$line, $city, $country]) {
            try {
                PersonAddress::add(self::ID, self::ID, $line, null, $city, null, null, $country, new DateTimeImmutable());
                self::fail("Accepted $line/$city/$country");
            } catch (PeopleException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function contact(ContactKind $kind, string $value): ContactMethod
    {
        return ContactMethod::add(self::ID, self::ID, $kind, $value, true, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }
}
