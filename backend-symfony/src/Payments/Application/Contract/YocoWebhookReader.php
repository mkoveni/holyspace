<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use SensitiveParameter;

interface YocoWebhookReader
{
    /** @return list<YocoWebhook> every subscription the key can see (both environments) */
    public function list(#[SensitiveParameter] string $secretKey): array;
}
