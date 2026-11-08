<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use SensitiveParameter;

interface YocoWebhookWriter
{
    public function register(#[SensitiveParameter] string $secretKey, string $name, string $callback, string $mode): RegisteredWebhook;

    public function remove(#[SensitiveParameter] string $secretKey, string $id): void;
}
