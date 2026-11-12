<?php

declare(strict_types=1);

namespace App\People\Application;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\MembershipConverter;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\MembershipType;
use Psr\Clock\ClockInterface;

final readonly class MembershipConversion implements MembershipConverter
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonRepository $people,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function admit(string $actorId, string $personId): bool
    {
        $this->authorization->requireActorPermission($actorId, 'people.manage');
        $person = $this->people->findForUpdate($personId) ?? throw PeopleException::missing('Person');
        if (MembershipType::ActiveMember === $person->membershipType) {
            return false;
        }
        $now = $this->clock->now();
        $this->people->update($person->admitAsMember($now));
        $this->audit->audit($actorId, 'person.visitor.converted', $person->id, $now);

        return true;
    }
}
