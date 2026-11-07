<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\VerifyGuardianEmail;

use App\ChildrenMinistry\Messaging\Application\DeliveryPolicy;
use App\ChildrenMinistry\Messaging\Domain\Repository\VerifiedEmailRepository;
use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** A coordinator records that the guardian confirmed one of their active People email contacts. Valid for at most a year. */
final readonly class VerifyGuardianEmailHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private DeliveryPolicy $policy,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private VerifiedEmailRepository $repository,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the verification id */
    public function handle(VerifyGuardianEmailCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');
        $proof = ChildrenRules::text($command->evidence, 500);

        $communication = $this->policy->current($command->communicationId, $this->clock->now());
        $this->people->lock($communication->guardianId) ?? throw ChildrenException::missing('Guardian');
        $now = $this->clock->now();
        if ($command->validUntil <= $now || $command->validUntil > $now->modify('+366 days')) {
            throw ChildrenException::invalid('Email verification must expire within one year.');
        }
        $contact = null;
        foreach ($this->emails->active($communication->guardianId) as $email) {
            if ($email->id === $command->contactId) {
                $contact = $email;
                break;
            }
        }
        if (null === $contact) {
            throw ChildrenException::invalid("Choose the guardian's active People email contact.");
        }
        $id = $this->ids->uuid();
        $this->repository->verify(new VerifiedGuardianEmail($id, $communication->guardianId, $contact->id, $contact->value, $command->actorId, $now, $command->validUntil, $proof, null));
        $this->audit->record($command->actorId, 'guardian.email.verified', $id, '', $now);

        return $id;
    }
}
