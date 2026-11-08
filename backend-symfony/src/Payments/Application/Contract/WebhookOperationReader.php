<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\WebhookOperation;

interface WebhookOperationReader
{
    /** @return list<WebhookOperation> started, uncertain or ready operations of the environment, newest first, at most 20 */
    public function pending(string $mode): array;
}
