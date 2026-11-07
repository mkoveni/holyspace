<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetEmailReview;

use App\ChildrenMinistry\Messaging\Application\Contract\VerifiedEmailReader;
use App\ChildrenMinistry\Messaging\Application\DeliveryPolicy;
use App\ChildrenMinistry\Messaging\Application\EmailRecipient;
use App\ChildrenMinistry\Messaging\Application\EmailReview;
use App\Communications\Application\Contract\EmailTransport;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonEmailDirectory;
use Psr\Clock\ClockInterface;

/** What a reviewer approves: the exact wording, each active address, and the fingerprint approval must quote. Sends nothing. */
final readonly class GetEmailReviewHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private DeliveryPolicy $policy,
        private VerifiedEmailReader $verifications,
        private PersonEmailDirectory $emails,
        private EmailTransport $transport,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetEmailReviewQuery $query): EmailReview
    {
        $this->authorization->requirePermission('children.safeguarding');
        $now = $this->clock->now();
        $communication = $this->policy->current($query->communicationId, $now);
        $rendered = $this->policy->render($communication);
        $verified = $this->verifications->emails($communication->guardianId);

        $recipients = [];
        foreach ($this->emails->active($communication->guardianId) as $email) {
            $match = null;
            foreach ($verified as $candidate) {
                if ($candidate->contactId === $email->id && $candidate->email === $email->value && $candidate->valid($now)) {
                    $match = $candidate;
                    break;
                }
            }
            $recipients[] = new EmailRecipient($email->id, $email->value, $match?->id, $match?->validUntil, null === $match ? null : $this->policy->hash($communication, $match, $rendered));
        }

        return new EmailReview($communication->id, $communication->version, $communication->message, $recipients, $this->transport->configuration(), $rendered->subject, $rendered->html, $rendered->key, $rendered->version);
    }
}
