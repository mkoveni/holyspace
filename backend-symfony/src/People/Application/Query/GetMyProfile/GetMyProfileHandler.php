<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetMyProfile;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\People\Application\ContactView;
use App\People\Application\Contract\PersonReader;
use App\People\Application\Contract\TitleReader;
use App\People\Application\MyProfile;
use App\People\Application\MyProfileResult;
use App\Shared\Application\ChurchCalendar;

/** The signed-in member's own profile, or an empty result while the church office has not verified their account. */
final readonly class GetMyProfileHandler
{
    public function __construct(
        private MemberIdentity $identity,
        private PersonReader $people,
        private TitleReader $titles,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(GetMyProfileQuery $query): MyProfileResult
    {
        $personId = $this->identity->currentPerson();
        $person = null === $personId ? null : $this->people->find($personId, $this->calendar->today());
        if (null === $person) {
            return new MyProfileResult(null, []);
        }

        return new MyProfileResult(
            new MyProfile(
                $person->id,
                $person->details,
                $person->membershipType->value,
                $person->version,
                array_values(array_filter($person->contacts, static fn (ContactView $contact): bool => null === $contact->endedAt)),
            ),
            $this->titles->titles(),
        );
    }
}
