<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Push;

use App\Communications\Application\Contract\PushSettings;
use SensitiveParameter;

/** Push is available only when enabled, with a 32-byte token key and an Expo project id (`PUSH_*` variables). */
final readonly class ExpoPushSettings implements PushSettings
{
    private bool $enabled;

    private ?string $project;

    public function __construct(bool $enabled, string $projectId, #[SensitiveParameter] string $tokenKey)
    {
        $key = base64_decode($tokenKey, true);
        $this->enabled = $enabled && false !== $key && 32 === strlen($key);
        $project = strtolower(trim($projectId));
        $this->project = 1 === preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $project) ? $project : null;
    }

    public function enabled(): bool
    {
        return $this->enabled && null !== $this->project;
    }

    public function projectId(): ?string
    {
        return $this->project;
    }
}
