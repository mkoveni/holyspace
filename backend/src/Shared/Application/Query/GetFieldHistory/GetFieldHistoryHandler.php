<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetFieldHistory;

use App\Shared\Application\Audit\AuditHistory;

final readonly class GetFieldHistoryHandler
{
    public function __construct(private AuditHistory $reader)
    {
    }

    public function __invoke(GetFieldHistoryQuery $message): array
    {
        return $this->reader->page($message->context, $message->id, $message->limit, $message->before);
    }
}
