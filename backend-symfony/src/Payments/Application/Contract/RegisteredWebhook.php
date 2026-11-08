<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use SensitiveParameter;

final readonly class RegisteredWebhook
{
    public function __construct(public string $id, #[SensitiveParameter] public string $secret)
    {
    }

    /** @return array{id: string, secret: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'secret' => 'redacted'];
    }
}
