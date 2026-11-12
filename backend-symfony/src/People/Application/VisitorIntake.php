<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\VisitorDetails;
use App\People\Application\Contract\VisitorPerson;
use App\People\Application\Contract\VisitorRegistrar;
use App\People\Domain\ContactMethod;
use App\People\Domain\PeopleException;
use App\People\Domain\Person;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\ContactKind;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** Matches a visitor to an existing person by contact, or registers a new VISITOR. Existing records are never edited. */
final readonly class VisitorIntake implements VisitorRegistrar
{
    public function __construct(
        private PersonRepository $people,
        private ContactIdentity $contacts,
        private PeopleAuditWriter $audit,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function registerOrFind(VisitorDetails $details): VisitorPerson
    {
        $mobile = self::blank($details->mobile);
        $email = self::blank($details->email);
        if (null === $mobile && null === $email) {
            throw PeopleException::invalid('Enter a mobile number or an email address.');
        }
        $now = $this->clock->now();
        $personId = $this->ids->uuid();
        $phone = null === $mobile ? null : ContactMethod::add($this->ids->uuid(), $personId, ContactKind::Phone, $mobile, true, $now);
        $mail = null === $email ? null : ContactMethod::add($this->ids->uuid(), $personId, ContactKind::Email, $email, true, $now);
        $person = Person::register($personId, new PersonDetails(null, $details->firstName, $details->lastName, null, null, null, null), MembershipType::Visitor, $this->calendar->today(), $now);

        // Always claim in the same order (mobile, then email) so concurrent intakes cannot deadlock.
        $known = null === $phone ? null : $this->contacts->claim(ContactKind::Phone, $phone->value);
        $known ??= null === $mail ? null : $this->contacts->claim(ContactKind::Email, $mail->value);
        if (null !== $known) {
            return new VisitorPerson($known, false);
        }

        $this->people->insert($person);
        foreach ([$phone, $mail] as $contact) {
            if (null !== $contact) {
                $this->people->addContact($contact);
            }
        }
        $this->audit->audit($person->id, 'person.visitor.registered', $person->id, $now);

        return new VisitorPerson($person->id, true);
    }

    private static function blank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : $value;
    }
}
