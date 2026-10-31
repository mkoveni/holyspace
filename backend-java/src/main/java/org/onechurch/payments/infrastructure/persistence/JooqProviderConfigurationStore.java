package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_CHECKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_REVISIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_WEBHOOK_OPERATIONS;

import org.jooq.DSLContext;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderSettings;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsConfigurationChecksRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsConfigurationRevisionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsProviderSettingsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqProviderConfigurationStore implements ProviderConfigurationReader, ProviderConfigurationRepository {
    private final DSLContext db;
    private final org.onechurch.identityaccess.application.contract.AccountNames accounts;
    public JooqProviderConfigurationStore(DSLContext db,
        org.onechurch.identityaccess.application.contract.AccountNames accounts) {
        this.db = db;
        this.accounts = accounts;
    }
    public boolean activeWebhookRemoval(String mode) {
        return db.fetchExists(db.selectOne()
            .from(PAYMENTS_WEBHOOK_OPERATIONS)
            .where(PAYMENTS_WEBHOOK_OPERATIONS.MODE.eq(mode)
                .and(PAYMENTS_WEBHOOK_OPERATIONS.KIND.eq("REMOVE"))
                .and(PAYMENTS_WEBHOOK_OPERATIONS.STATUS.in("STARTED", "UNKNOWN"))));
    }
    private ProviderSettings settings(PaymentsProviderSettingsRecord r) {
        return new ProviderSettings(r.getProvider(), r.getMode(), r.getEnabled(), r.getMonthly(), r.getManaged(),
            r.getVersion());
    }
    private ProviderConfiguration configuration(PaymentsConfigurationRevisionsRecord r) {
        return new ProviderConfiguration(r.getId(), r.getProvider(), r.getMode(), r.getSequence(), r.getPublicUrl(),
            r.getIpRanges(), r.getEncryptedCredentials(), r.getCredentialHash(), r.getMerchantIdSet(),
            r.getMerchantKeySet(), r.getPassphraseSet(), r.getYocoKeySet(), r.getWebhookSecretSet(), r.getCreatedBy(),
            r.getCreatedAt().toInstant(), r.getBackendUrl());
    }
    private ConfigurationCheck check(PaymentsConfigurationChecksRecord r) {
        return new ConfigurationCheck(r.getId(), r.getRevisionId(), r.getSuccessful(), r.getMessage(), r.getCheckedBy(),
            r.getCheckedAt().toInstant());
    }
    @Override
    public ProviderSettings lock(String provider) {
        return db.selectFrom(PAYMENTS_PROVIDER_SETTINGS)
            .where(PAYMENTS_PROVIDER_SETTINGS.PROVIDER.eq(provider))
            .forUpdate()
            .fetchOptional()
            .map(this::settings)
            .orElseThrow(() -> PaymentException.invalid("Choose a supported provider."));
    }
    @Override
    public Optional<ProviderSettings> settings(String provider) {
        return db.selectFrom(PAYMENTS_PROVIDER_SETTINGS)
            .where(PAYMENTS_PROVIDER_SETTINGS.PROVIDER.eq(provider))
            .fetchOptional()
            .map(this::settings);
    }
    @Override
    public Optional<ProviderConfiguration> latest(String provider, String mode) {
        return db.selectFrom(PAYMENTS_CONFIGURATION_REVISIONS)
            .where(PAYMENTS_CONFIGURATION_REVISIONS.PROVIDER.eq(provider)
                .and(PAYMENTS_CONFIGURATION_REVISIONS.MODE.eq(mode)))
            .orderBy(PAYMENTS_CONFIGURATION_REVISIONS.SEQUENCE.desc())
            .limit(1)
            .fetchOptional()
            .map(this::configuration);
    }
    @Override
    public Optional<ProviderConfiguration> revision(UUID id) {
        return db.selectFrom(PAYMENTS_CONFIGURATION_REVISIONS)
            .where(PAYMENTS_CONFIGURATION_REVISIONS.ID.eq(id))
            .fetchOptional()
            .map(this::configuration);
    }
    @Override
    public Optional<ConfigurationCheck> latestCheck(UUID revision) {
        return db.selectFrom(PAYMENTS_CONFIGURATION_CHECKS)
            .where(PAYMENTS_CONFIGURATION_CHECKS.REVISION_ID.eq(revision))
            .orderBy(PAYMENTS_CONFIGURATION_CHECKS.CHECKED_AT.desc(), PAYMENTS_CONFIGURATION_CHECKS.ID)
            .limit(1)
            .fetchOptional()
            .map(this::check);
    }
    @Override
    public void insert(ProviderConfiguration c) {
        db.insertInto(PAYMENTS_CONFIGURATION_REVISIONS)
            .set(PAYMENTS_CONFIGURATION_REVISIONS.ID, c.id())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.PROVIDER, c.provider())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.MODE, c.mode())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.SEQUENCE, c.sequence())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.PUBLIC_URL, c.publicUrl())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.BACKEND_URL, c.backendUrl())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.IP_RANGES, c.ipRanges())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.ENCRYPTED_CREDENTIALS, c.encryptedCredentials())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.CREDENTIAL_HASH, c.credentialHash())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.MERCHANT_ID_SET, c.merchantIdSet())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.MERCHANT_KEY_SET, c.merchantKeySet())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.PASSPHRASE_SET, c.passphraseSet())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.YOCO_KEY_SET, c.yocoKeySet())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.WEBHOOK_SECRET_SET, c.webhookSecretSet())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.CREATED_BY, c.createdBy())
            .set(PAYMENTS_CONFIGURATION_REVISIONS.CREATED_AT, c.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public void save(ProviderSettings s) {
        int count = db.update(PAYMENTS_PROVIDER_SETTINGS)
            .set(PAYMENTS_PROVIDER_SETTINGS.MODE, s.mode())
            .set(PAYMENTS_PROVIDER_SETTINGS.ENABLED, s.enabled())
            .set(PAYMENTS_PROVIDER_SETTINGS.MONTHLY, s.monthly())
            .set(PAYMENTS_PROVIDER_SETTINGS.MANAGED, s.managed())
            .set(PAYMENTS_PROVIDER_SETTINGS.VERSION, s.version())
            .where(PAYMENTS_PROVIDER_SETTINGS.PROVIDER.eq(s.provider())
                .and(PAYMENTS_PROVIDER_SETTINGS.VERSION.eq(s.version() - 1)))
            .execute();
        if (count != 1) {
            throw PaymentException.conflict("Payment settings changed. Reload before saving.");
        }
    }
    @Override
    public void check(ConfigurationCheck c) {
        db.insertInto(PAYMENTS_CONFIGURATION_CHECKS)
            .set(PAYMENTS_CONFIGURATION_CHECKS.ID, c.id())
            .set(PAYMENTS_CONFIGURATION_CHECKS.REVISION_ID, c.revisionId())
            .set(PAYMENTS_CONFIGURATION_CHECKS.SUCCESSFUL, c.successful())
            .set(PAYMENTS_CONFIGURATION_CHECKS.MESSAGE, c.message())
            .set(PAYMENTS_CONFIGURATION_CHECKS.CHECKED_BY, c.checkedBy())
            .set(PAYMENTS_CONFIGURATION_CHECKS.CHECKED_AT, c.checkedAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public void audit(String provider, String mode, long version, String action, String note, String fields, UUID actor,
        Instant now) {
        db.insertInto(PAYMENTS_CONFIGURATION_HISTORY)
            .set(PAYMENTS_CONFIGURATION_HISTORY.ID, UUID.randomUUID())
            .set(PAYMENTS_CONFIGURATION_HISTORY.PROVIDER, provider)
            .set(PAYMENTS_CONFIGURATION_HISTORY.MODE, mode)
            .set(PAYMENTS_CONFIGURATION_HISTORY.VERSION, version)
            .set(PAYMENTS_CONFIGURATION_HISTORY.ACTION, action)
            .set(PAYMENTS_CONFIGURATION_HISTORY.NOTE, note)
            .set(PAYMENTS_CONFIGURATION_HISTORY.CHANGED_FIELDS, fields)
            .set(PAYMENTS_CONFIGURATION_HISTORY.ACTOR_ID, actor)
            .set(PAYMENTS_CONFIGURATION_HISTORY.OCCURRED_AT, now.atOffset(ZoneOffset.UTC))
            .execute();
    }
    private Environment environment(String provider, String mode) {
        return latest(provider, mode)
            .map(c -> new Environment(mode, c.id(), c.publicUrl(), c.ipRanges(), c.merchantIdSet(), c.merchantKeySet(),
                c.passphraseSet(), c.yocoKeySet(), c.webhookSecretSet(),
                provider.equals("payfast")
                    ? c.merchantIdSet() && c.merchantKeySet() && c.passphraseSet() && !c.ipRanges().isBlank()
                    : c.yocoKeySet() && c.webhookSecretSet(),
                latestCheck(c.id()).orElse(null), c.backendUrl()))
            .orElseGet(() -> new Environment(mode, null, "", "", false, false, false, false, false, false, null, ""));
    }
    @Override
    public List<Provider> settings() {
        return db.selectFrom(PAYMENTS_PROVIDER_SETTINGS)
            .orderBy(PAYMENTS_PROVIDER_SETTINGS.PROVIDER)
            .fetch(r -> new Provider(settings(r),
                List.of(environment(r.getProvider(), "test"), environment(r.getProvider(), "live"))));
    }
    @Override
    public List<Change> history() {
        java.util.List<org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsConfigurationHistoryRecord> rows =
            db.selectFrom(PAYMENTS_CONFIGURATION_HISTORY)
                .orderBy(PAYMENTS_CONFIGURATION_HISTORY.OCCURRED_AT.desc(), PAYMENTS_CONFIGURATION_HISTORY.ID)
                .limit(100)
                .fetch();
        java.util.Map<UUID, String> names = accounts.names(rows.stream()
            .map(org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsConfigurationHistoryRecord::getActorId)
            .distinct()
            .toList());
        return rows.stream()
            .map(r -> new Change(r.getProvider(), r.getMode(), r.getVersion(), r.getAction(), r.getNote(),
                r.getChangedFields(), r.getActorId(), names.getOrDefault(r.getActorId(), "Former account"),
                r.getOccurredAt().toInstant()))
            .toList();
    }
}
