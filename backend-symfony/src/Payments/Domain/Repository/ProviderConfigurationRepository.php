<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderSettings;
use DateTimeImmutable;

/** Provider settings heads, immutable configuration revisions, connection checks and configuration history. */
interface ProviderConfigurationRepository
{
    /** Whether a Yoco webhook removal in this environment is started or uncertain. */
    public function activeWebhookRemoval(string $mode): bool;

    public function revision(string $id): ?ProviderConfiguration;

    /** Locks the provider's settings head; 400 for an unknown provider. */
    public function lock(string $provider): ProviderSettings;

    public function latest(string $provider, string $mode): ?ProviderConfiguration;

    public function latestCheck(string $revisionId): ?ConfigurationCheck;

    public function insert(ProviderConfiguration $configuration): void;

    /** Saves the head over its previous version; 409 when it changed. */
    public function save(ProviderSettings $settings): void;

    public function check(ConfigurationCheck $check): void;

    public function audit(string $provider, string $mode, int $version, string $action, string $note, string $changedFields, string $actorId, DateTimeImmutable $now): void;
}
