<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Application\Contract\MembershipView;
use App\Communities\Application\Contract\ReviewedNotice;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonEmailDirectory;
use Psr\Clock\ClockInterface;

/**
 * Java `CommunityMessaging` (contacts and review): renders a notice for one current member and one of their current
 * addresses without sending anything. The hash binds the membership, community, address and template revision.
 */
final readonly class NoticeReview
{
    public const string TEMPLATE = 'communities.notice';

    public function __construct(
        private CommunityReader $reader,
        private CommunityAccess $access,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private EmailTemplates $templates,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<PersonEmail> */
    public function contacts(string $membershipId): array
    {
        $membership = $this->current($membershipId);
        $this->access->require($membership->communityId);

        return $this->emails->active($membership->personId);
    }

    public function review(string $membershipId, string $contactId, ?string $message): ReviewedNotice
    {
        $membership = $this->current($membershipId);
        $this->access->require($membership->communityId);
        $community = $this->access->community($membership->communityId);
        $recipient = null;
        foreach ($this->emails->active($membership->personId) as $email) {
            if ($email->id === $contactId) {
                $recipient = $email->value;
                break;
            }
        }
        if (null === $recipient) {
            throw CommunityException::conflict("Choose this member's current email address.");
        }
        $person = $this->people->get($membership->personId) ?? throw CommunityException::missing();
        $rendered = $this->templates->render(self::TEMPLATE, ['name' => $person->name, 'community' => $community->name, 'message' => CommunityRules::text($message, 5000)]);

        return new ReviewedNotice(
            self::hash($membership, $community, $contactId, $recipient, $rendered->subject, $rendered->html, $rendered->version),
            $recipient,
            $rendered->subject,
            $rendered->html,
            $rendered->version,
        );
    }

    /** Java `CommunityMessaging.hash`; Communities' eligibility recomputes it before every send. */
    public static function hash(MembershipView $membership, CommunityView $community, string $contactId, string $recipient, string $subject, string $html, int $templateVersion): string
    {
        return CommunityHash::of($membership->id, $membership->version, $community->id, $community->version, $contactId, $recipient, $subject, $html, $templateVersion);
    }

    private function current(string $membershipId): MembershipView
    {
        $membership = $this->reader->membership($membershipId) ?? throw CommunityException::missing();
        $community = $this->access->community($membership->communityId);
        if (!$membership->covers($this->clock->now()) || !$community->active || !($this->people->get($membership->personId)->available ?? false)) {
            throw CommunityException::conflict('Notices require a current, available member of an active community.');
        }

        return $membership;
    }
}
