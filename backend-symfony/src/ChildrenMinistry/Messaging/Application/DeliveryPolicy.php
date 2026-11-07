<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\ChildrenMinistry\Messaging\Application\Contract\GuardianCommunicationReader;
use App\ChildrenMinistry\Messaging\Application\Contract\VerifiedEmailReader;
use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;
use App\ChildrenMinistry\Shared\Application\RequestFingerprint;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailTemplates;
use App\Communications\Application\Contract\RenderedEmail;
use App\People\Application\Contract\PersonEmailDirectory;
use DateTimeImmutable;

/**
 * Everything that binds an approval to what was reviewed: the draft must still be eligible, the verification must be
 * current for the guardian's unchanged active contact, and the fingerprint must match the wording and recipient.
 */
final readonly class DeliveryPolicy
{
    public function __construct(
        private GuardianCommunicationPolicy $policy,
        private GuardianCommunicationReader $communications,
        private VerifiedEmailReader $verifications,
        private PersonEmailDirectory $emails,
        private EmailTemplates $templates,
    ) {
    }

    public function current(string $id, DateTimeImmutable $now): CommunicationView
    {
        $communication = $this->communications->find($id) ?? throw ChildrenException::missing('Communication');
        if (CommunicationStatus::Draft !== $communication->status) {
            throw ChildrenException::conflict('Only draft communications can be emailed.');
        }
        $this->policy->guardian($communication->childId, $communication->authorityId, $now);
        $programme = $this->policy->programme($communication->programmeId, $communication->childId, $communication->type->value, $communication->sessionId, $now);
        if (CommunicationType::ConsentRenewal === $communication->type && $communication->policyId !== $programme->currentPolicyId) {
            throw ChildrenException::conflict('The programme consent policy changed. Prepare a current request.');
        }

        return $communication;
    }

    public function verified(string $id, CommunicationView $communication, DateTimeImmutable $now): VerifiedGuardianEmail
    {
        $verification = $this->verifications->verification($id);
        if (null !== $verification && $verification->guardianId === $communication->guardianId && $verification->valid($now)) {
            foreach ($this->emails->active($communication->guardianId) as $email) {
                if ($email->id === $verification->contactId && $email->value === $verification->email) {
                    return $verification;
                }
            }
        }

        throw ChildrenException::conflict("A current verification of the guardian's unchanged active email contact is required.");
    }

    public function render(CommunicationView $communication): RenderedEmail
    {
        return $this->templates->render(CommunicationType::ConsentRenewal === $communication->type ? 'children.consent-renewal' : 'children.session-reminder', $this->policy->variables($communication));
    }

    public function hash(CommunicationView $communication, VerifiedGuardianEmail $email, RenderedEmail $rendered): string
    {
        return RequestFingerprint::of($this->sourceHash($communication, $email), $rendered->subject, $rendered->html, $rendered->key, $rendered->version);
    }

    /** Re-judges an approved delivery just before it is sent. */
    public function recheck(DeliverySnapshot $delivery, DateTimeImmutable $now): void
    {
        $communication = $this->current($delivery->communicationId, $now);
        $email = $this->verified((string) $delivery->verificationId, $communication, $now);
        $expected = 'text/plain' === $delivery->contentType
            ? $this->sourceHash($communication, $email)
            : RequestFingerprint::of($this->sourceHash($communication, $email), $delivery->subject, $delivery->body, $delivery->templateKey, $delivery->templateVersion);
        if ($delivery->reviewHash !== $expected) {
            throw ChildrenException::conflict('The reviewed message or recipient changed.');
        }
    }

    /** The guardian and programme must still qualify at the scheduled time. */
    public function schedule(CommunicationView $communication, DateTimeImmutable $at): void
    {
        $this->policy->guardian($communication->childId, $communication->authorityId, $at);
        $this->policy->programme($communication->programmeId, $communication->childId, $communication->type->value, $communication->sessionId, $at);
    }

    private function sourceHash(CommunicationView $communication, VerifiedGuardianEmail $email): string
    {
        return RequestFingerprint::of($communication->id, $communication->version, $communication->message, $email->id, $email->email, $email->contactId);
    }
}
