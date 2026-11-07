<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Domain\EmailDelivery;

final class DeliveryMapping
{
    private function __construct()
    {
    }

    public static function snapshot(EmailDelivery $d): DeliverySnapshot
    {
        return new DeliverySnapshot($d->id, $d->communicationId, $d->verificationId, $d->contactId, $d->recipient, $d->subject, $d->body, $d->reviewHash, $d->approvedBy, $d->approvedAt, $d->scheduledAt, $d->status, $d->attempts, $d->messageId, $d->attemptId, $d->startedAt, $d->finishedAt, $d->errorCode, $d->captured, $d->version, $d->contentType, $d->templateKey, $d->templateVersion);
    }

    public static function aggregate(DeliverySnapshot $d): EmailDelivery
    {
        return new EmailDelivery($d->id, $d->communicationId, $d->verificationId, $d->contactId, $d->recipient, $d->subject, $d->body, $d->reviewHash, $d->approvedBy, $d->approvedAt, $d->scheduledAt, $d->status, $d->attempts, $d->messageId, $d->attemptId, $d->startedAt, $d->finishedAt, $d->errorCode, $d->captured, $d->version, $d->contentType, $d->templateKey, $d->templateVersion);
    }
}
