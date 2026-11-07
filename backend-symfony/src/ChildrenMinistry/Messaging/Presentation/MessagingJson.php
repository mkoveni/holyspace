<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation;

use App\ChildrenMinistry\Messaging\Application\CommunicationListView;
use App\ChildrenMinistry\Messaging\Application\CommunicationRow;
use App\ChildrenMinistry\Messaging\Application\CommunicationView;
use App\ChildrenMinistry\Messaging\Application\EmailDeliveryView;
use App\ChildrenMinistry\Messaging\Application\EmailRecipient;
use App\ChildrenMinistry\Messaging\Application\EmailReview;
use App\Communications\Application\Contract\DeliveryEvent;
use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\DeliverySummary;
use App\Shared\Presentation\Json;

/** Wire format of guardian communications and email deliveries, matching the Java API's JSON field names. */
final class MessagingJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function communication(CommunicationView $c): array
    {
        return [
            'id' => $c->id,
            'childId' => $c->childId,
            'authorityId' => $c->authorityId,
            'guardianId' => $c->guardianId,
            'programmeId' => $c->programmeId,
            'policyId' => $c->policyId,
            'sessionId' => $c->sessionId,
            'type' => $c->type->value,
            'message' => $c->message,
            'dueOn' => $c->dueOn->iso(),
            'status' => $c->status->value,
            'createdBy' => $c->createdBy,
            'createdAt' => Json::instant($c->createdAt),
            'updatedBy' => $c->updatedBy,
            'updatedAt' => Json::instant($c->updatedAt),
            'contactReference' => $c->contactReference,
            'version' => $c->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function communications(CommunicationListView $view): array
    {
        return [
            'communications' => array_map(static fn (CommunicationRow $row): array => [
                'communication' => [
                    'id' => $row->communication->id,
                    'childId' => $row->communication->childId,
                    'guardianId' => $row->communication->guardianId,
                    'programmeId' => $row->communication->programmeId,
                    'sessionId' => $row->communication->sessionId,
                    'type' => $row->communication->type->value,
                    'dueOn' => $row->communication->dueOn->iso(),
                    'status' => $row->communication->status->value,
                    'version' => $row->communication->version,
                ],
                'childName' => $row->childName,
                'guardianName' => $row->guardianName,
                'delivery' => null === $row->delivery ? null : self::summary($row->delivery),
            ], $view->communications),
            'total' => $view->total,
            'overdue' => $view->overdue,
        ];
    }

    /** @return array<string, mixed> */
    public static function review(EmailReview $review): array
    {
        return [
            'communicationId' => $review->communicationId,
            'version' => $review->version,
            'message' => $review->message,
            'recipients' => array_map(static fn (EmailRecipient $r): array => [
                'contactId' => $r->contactId,
                'email' => $r->email,
                'verificationId' => $r->verificationId,
                'validUntil' => null === $r->validUntil ? null : Json::instant($r->validUntil),
                'reviewHash' => $r->reviewHash,
            ], $review->recipients),
            'transport' => ['enabled' => $review->transport->enabled, 'capture' => $review->transport->capture, 'mode' => $review->transport->mode],
            'subject' => $review->subject,
            'html' => $review->html,
            'templateKey' => $review->templateKey,
            'templateVersion' => $review->templateVersion,
        ];
    }

    /** @return array<string, mixed> */
    public static function deliveries(DeliveryPage $page): array
    {
        return ['deliveries' => array_map(self::summary(...), $page->deliveries), 'total' => $page->total, 'failed' => $page->failed, 'waiting' => $page->waiting];
    }

    /** @return array<string, mixed> restricted: recipient and approved wording */
    public static function delivery(EmailDeliveryView $view): array
    {
        return ['delivery' => self::snapshot($view->delivery), 'events' => array_map(static fn (DeliveryEvent $e): array => ['id' => $e->id, 'type' => $e->type, 'occurredAt' => Json::instant($e->occurredAt), 'reference' => $e->reference], $view->events)];
    }

    /** @return array<string, mixed> */
    public static function summary(DeliverySummary $s): array
    {
        return ['id' => $s->id, 'communicationId' => $s->communicationId, 'status' => $s->status, 'scheduledAt' => Json::instant($s->scheduledAt), 'attempts' => $s->attempts, 'errorCode' => $s->errorCode, 'captured' => $s->captured, 'version' => $s->version];
    }

    /** @return array<string, mixed> */
    public static function snapshot(DeliverySnapshot $d): array
    {
        return [
            'id' => $d->id,
            'communicationId' => $d->communicationId,
            'verificationId' => $d->verificationId,
            'contactId' => $d->contactId,
            'recipient' => $d->recipient,
            'subject' => $d->subject,
            'body' => $d->body,
            'reviewHash' => $d->reviewHash,
            'approvedBy' => $d->approvedBy,
            'approvedAt' => Json::instant($d->approvedAt),
            'scheduledAt' => Json::instant($d->scheduledAt),
            'status' => $d->status,
            'attempts' => $d->attempts,
            'messageId' => $d->messageId,
            'attemptId' => $d->attemptId,
            'startedAt' => null === $d->startedAt ? null : Json::instant($d->startedAt),
            'finishedAt' => null === $d->finishedAt ? null : Json::instant($d->finishedAt),
            'errorCode' => $d->errorCode,
            'captured' => $d->captured,
            'version' => $d->version,
            'contentType' => $d->contentType,
            'templateKey' => $d->templateKey,
            'templateVersion' => $d->templateVersion,
        ];
    }
}
