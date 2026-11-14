<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\AccountNames;
use App\Payments\Application\Contract\ConfigurationChange;
use App\Payments\Application\Contract\ProviderConfigurationReader;
use App\Payments\Application\Contract\ProviderEnvironment;
use App\Payments\Application\Contract\ProviderSettingsView;
use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderSettings;
use App\Payments\Domain\Repository\ProviderConfigurationRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/** Provider settings heads, immutable revisions (V22), connection checks and configuration history. */
final readonly class DbalProviderConfigurationStore implements ProviderConfigurationReader, ProviderConfigurationRepository
{
    private const string REVISION = 'id, provider, mode, sequence, public_url, ip_ranges, encrypted_credentials, credential_hash, merchant_id_set, merchant_key_set, passphrase_set, yoco_key_set, webhook_secret_set, created_by, created_at, backend_url';

    private PaymentWrites $writes;

    public function __construct(private Connection $connection, private AccountNames $accounts)
    {
        $this->writes = new PaymentWrites($connection);
    }

    public function activeWebhookRemoval(string $mode): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM payments_webhook_operations WHERE mode = ? AND kind = 'REMOVE' AND status IN ('STARTED', 'UNKNOWN') LIMIT 1",
            [$mode],
        );
    }

    public function lock(string $provider): ProviderSettings
    {
        $row = $this->connection->fetchAssociative('SELECT provider, mode, enabled, monthly, managed, version FROM payments_provider_settings WHERE provider = ? FOR UPDATE', [$provider]);

        return false === $row ? throw PaymentException::invalid('Choose a supported provider.') : self::headRow($row);
    }

    public function head(string $provider): ?ProviderSettings
    {
        $row = $this->connection->fetchAssociative('SELECT provider, mode, enabled, monthly, managed, version FROM payments_provider_settings WHERE provider = ?', [$provider]);

        return false === $row ? null : self::headRow($row);
    }

    public function latest(string $provider, string $mode): ?ProviderConfiguration
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::REVISION.' FROM payments_configuration_revisions WHERE provider = ? AND mode = ? ORDER BY sequence DESC LIMIT 1',
            [$provider, $mode],
        );

        return false === $row ? null : self::revisionRow($row);
    }

    public function revision(string $id): ?ProviderConfiguration
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::REVISION.' FROM payments_configuration_revisions WHERE id = ?', [$id]);

        return false === $row ? null : self::revisionRow($row);
    }

    public function latestCheck(string $revisionId): ?ConfigurationCheck
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, revision_id, successful, message, checked_by, checked_at FROM payments_configuration_checks WHERE revision_id = ? ORDER BY checked_at DESC, id LIMIT 1',
            [$revisionId],
        );

        return false === $row ? null : new ConfigurationCheck(
            (string) $row['id'],
            (string) $row['revision_id'],
            (bool) $row['successful'],
            (string) $row['message'],
            (string) $row['checked_by'],
            Timestamps::fromDatabase((string) $row['checked_at'])
        );
    }

    public function insert(ProviderConfiguration $c): void
    {
        $this->writes->execute(
            'INSERT INTO payments_configuration_revisions (id, provider, mode, sequence, public_url, backend_url, ip_ranges, encrypted_credentials, credential_hash,
                merchant_id_set, merchant_key_set, passphrase_set, yoco_key_set, webhook_secret_set, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$c->id, $c->provider, $c->mode, $c->sequence, $c->publicUrl, $c->backendUrl, $c->ipRanges, $c->encryptedCredentials, $c->credentialHash,
                $c->merchantIdSet, $c->merchantKeySet, $c->passphraseSet, $c->yocoKeySet, $c->webhookSecretSet, $c->createdBy, Timestamps::toDatabase($c->createdAt)],
            [3 => ParameterType::INTEGER, 9 => ParameterType::BOOLEAN, 10 => ParameterType::BOOLEAN, 11 => ParameterType::BOOLEAN, 12 => ParameterType::BOOLEAN, 13 => ParameterType::BOOLEAN],
        );
    }

    public function save(ProviderSettings $s): void
    {
        $count = $this->writes->execute(
            'UPDATE payments_provider_settings SET mode = ?, enabled = ?, monthly = ?, managed = ?, version = ? WHERE provider = ? AND version = ?',
            [$s->mode, $s->enabled, $s->monthly, $s->managed, $s->version, $s->provider, $s->version - 1],
            [1 => ParameterType::BOOLEAN, 2 => ParameterType::BOOLEAN, 3 => ParameterType::BOOLEAN, 4 => ParameterType::INTEGER, 6 => ParameterType::INTEGER],
        );
        if (1 !== $count) {
            throw PaymentException::conflict('Payment settings changed. Reload before saving.');
        }
    }

    public function check(ConfigurationCheck $c): void
    {
        $this->writes->execute(
            'INSERT INTO payments_configuration_checks (id, revision_id, successful, message, checked_by, checked_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$c->id, $c->revisionId, $c->successful, $c->message, $c->checkedBy, Timestamps::toDatabase($c->checkedAt)],
            [2 => ParameterType::BOOLEAN],
        );
    }

    public function audit(string $provider, string $mode, int $version, string $action, string $note, string $changedFields, string $actorId, DateTimeImmutable $now): void
    {
        $this->writes->execute(
            'INSERT INTO payments_configuration_history (id, provider, mode, version, action, note, changed_fields, actor_id, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Uuid::v4()->toRfc4122(), $provider, $mode, $version, $action, $note, $changedFields, $actorId, Timestamps::toDatabase($now)],
            [3 => ParameterType::INTEGER],
        );
    }

    public function settings(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT provider, mode, enabled, monthly, managed, version FROM payments_provider_settings WHERE provider = \'yoco\' ORDER BY provider');

        return array_map(fn (array $row): ProviderSettingsView => new ProviderSettingsView(self::headRow($row), [
            $this->environment((string) $row['provider'], 'test'),
            $this->environment((string) $row['provider'], 'live'),
        ]), $rows);
    }

    public function history(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT provider, mode, version, action, note, changed_fields, actor_id, occurred_at FROM payments_configuration_history ORDER BY occurred_at DESC, id LIMIT 100',
        );
        $names = $this->accounts->names(array_values(array_unique(array_map(static fn (array $r): string => (string) $r['actor_id'], $rows))));

        return array_map(static fn (array $r): ConfigurationChange => new ConfigurationChange(
            (string) $r['provider'],
            (string) $r['mode'],
            (int) $r['version'],
            (string) $r['action'],
            (string) $r['note'],
            (string) $r['changed_fields'],
            (string) $r['actor_id'],
            $names[(string) $r['actor_id']] ?? 'Former account',
            Timestamps::fromDatabase((string) $r['occurred_at'])
        ), $rows);
    }

    private function environment(string $provider, string $mode): ProviderEnvironment
    {
        $c = $this->latest($provider, $mode);
        if (null === $c) {
            return new ProviderEnvironment($mode, null, '', '', false, false, false, false, false, false, null, '');
        }

        return new ProviderEnvironment(
            $mode,
            $c->id,
            $c->publicUrl,
            $c->ipRanges,
            $c->merchantIdSet,
            $c->merchantKeySet,
            $c->passphraseSet,
            $c->yocoKeySet,
            $c->webhookSecretSet,
            $c->complete(),
            $this->latestCheck($c->id),
            $c->backendUrl
        );
    }

    /** @param array<string, mixed> $r */
    private static function headRow(array $r): ProviderSettings
    {
        return new ProviderSettings((string) $r['provider'], (string) $r['mode'], (bool) $r['enabled'], (bool) $r['monthly'], (bool) $r['managed'], (int) $r['version']);
    }

    /** @param array<string, mixed> $r */
    private static function revisionRow(array $r): ProviderConfiguration
    {
        return new ProviderConfiguration(
            (string) $r['id'],
            (string) $r['provider'],
            (string) $r['mode'],
            (int) $r['sequence'],
            (string) $r['public_url'],
            (string) $r['ip_ranges'],
            (string) $r['encrypted_credentials'],
            (string) $r['credential_hash'],
            (bool) $r['merchant_id_set'],
            (bool) $r['merchant_key_set'],
            (bool) $r['passphrase_set'],
            (bool) $r['yoco_key_set'],
            (bool) $r['webhook_secret_set'],
            (string) $r['created_by'],
            Timestamps::fromDatabase((string) $r['created_at']),
            PaymentRows::text($r['backend_url'])
        );
    }
}
