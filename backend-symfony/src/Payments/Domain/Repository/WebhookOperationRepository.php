<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\WebhookOperation;

interface WebhookOperationRepository
{
    public function find(string $id): ?WebhookOperation;

    public function insert(WebhookOperation $operation): void;

    public function finish(string $id, string $status, ?string $webhookId, ?string $encryptedSecret): void;

    /** Whether any Yoco payment in this environment still relies on the callback at this backend origin. */
    public function referenced(string $mode, string $backendUrl): bool;
}
