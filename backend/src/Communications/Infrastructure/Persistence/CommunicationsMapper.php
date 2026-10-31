<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Domain\Model\Campaign;
use App\Communications\Domain\Model\CommunicationPreference;
use App\Communications\Domain\Model\MessageDelivery;
use App\Communications\Domain\Model\MessageTemplate;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class CommunicationsMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['templates' => MessageTemplate::class, 'preferences' => CommunicationPreference::class, 'campaigns' => Campaign::class, 'deliveries' => MessageDelivery::class];
    }
}
