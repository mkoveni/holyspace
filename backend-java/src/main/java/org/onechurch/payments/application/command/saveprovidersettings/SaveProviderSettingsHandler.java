package org.onechurch.payments.application.command.saveprovidersettings;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.contract.LegacyPaymentConfiguration;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConnectionProbe;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
import org.onechurch.payments.domain.ProviderSettings;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Duration;
import java.util.UUID;
public final class SaveProviderSettingsHandler {
    private final PaymentAccess access;
    private final ProviderConfigurationRepository repository;
    private final PaymentCredentialVault vault;
    private final LegacyPaymentConfiguration legacy;
    private final ProviderConnectionProbe probe;
    private final UnitOfWork tx;
    private final Clock clock;
    public SaveProviderSettingsHandler(PaymentAccess access, ProviderConfigurationRepository repository,
        PaymentCredentialVault vault, LegacyPaymentConfiguration legacy, ProviderConnectionProbe probe, UnitOfWork tx,
        Clock clock) {
        this.access = access;
        this.repository = repository;
        this.vault = vault;
        this.legacy = legacy;
        this.probe = probe;
        this.tx = tx;
        this.clock = clock;
    }
    public void handle(SaveProviderSettingsCommand c) {
        access.configure(c.actorId());
        ProviderSettings.validate(c.provider(), c.mode());
        String reason = org.onechurch.payments.domain.PaymentOrder.text(c.reason(), 1000, "Change reason");
        tx.execute(() -> {
            ProviderSettings current = repository.lock(c.provider());
            access.configure(c.actorId());
            current.expected(c.expectedVersion());
            ProviderSettings updated =
                new ProviderSettings(c.provider(), c.mode(), c.enabled(), c.monthly(), true, current.version() + 1);
            ProviderConfiguration prior = repository.latest(c.provider(), c.mode()).orElse(null);
            String origin = ProviderConfiguration.origin(c.publicUrl());
            String backendOrigin = c.backendUrl() == null ? (prior == null ? origin : prior.backendUrl())
                : ProviderConfiguration.origin(c.backendUrl());
            String networks = c.provider().equals("payfast") ? (c.ipRanges() == null ? "" : c.ipRanges().trim()) : "";
            if (!networks.isEmpty()) {
                probe.validateNetworks(networks);
            }
            ProviderCredentials before = prior == null ? legacy.credentials(c.provider(), c.mode())
                : vault.decrypt(prior.encryptedCredentials());
            ProviderCredentials replacements =
                c.replacements() == null ? new ProviderCredentials(null, null, null, null, null) : c.replacements();
            ProviderCredentials credentials = before.replace(replacements);
            if (c.provider().equals("payfast")) {
                credentials = new ProviderCredentials(credentials.merchantId(), credentials.merchantKey(),
                    credentials.passphrase(), null, null);
            } else {
                credentials =
                    new ProviderCredentials(null, null, null, credentials.yocoKey(), credentials.webhookSecret());
            }
            java.util.List<String> protectedValues =
                java.util.List.of(before.merchantId(), before.merchantKey(), before.passphrase(), before.yocoKey(),
                    before.webhookSecret(), credentials.merchantId(), credentials.merchantKey(),
                    credentials.passphrase(), credentials.yocoKey(), credentials.webhookSecret());
            if (protectedValues.stream().filter(value -> value.length() >= 4).anyMatch(reason::contains)) {
                throw PaymentException.invalid("Keep credentials out of the change reason.");
            }
            String hash = PaymentHash.of(credentials.merchantId(), credentials.merchantKey(), credentials.passphrase(),
                credentials.yocoKey(), credentials.webhookSecret());
            boolean changed = prior == null || !prior.publicUrl().equals(origin) || !prior.ipRanges().equals(networks)
                || !prior.backendUrl().equals(backendOrigin) || !prior.credentialHash().equals(hash);
            ProviderConfiguration revision = prior;
            if (changed) {
                revision = new ProviderConfiguration(UUID.randomUUID(), c.provider(), c.mode(),
                    prior == null ? 1 : prior.sequence() + 1, origin, networks, vault.encrypt(credentials), hash,
                    !credentials.merchantId().isBlank(), !credentials.merchantKey().isBlank(),
                    !credentials.passphrase().isBlank(), !credentials.yocoKey().isBlank(),
                    !credentials.webhookSecret().isBlank(), c.actorId(), clock.instant(), backendOrigin);
                if (c.enabled()) {
                    throw PaymentException.conflict(
                        "Save changed connection details with new checkouts disabled, test the saved connection, then enable the provider.");
                }
                repository.insert(revision);
            }
            if (c.enabled()) {
                if (c.provider().equals("yoco") && repository.activeWebhookRemoval(c.mode())) {
                    throw PaymentException.conflict("Resolve the pending webhook removal before activation.");
                }
                if (!vault.ready() || !credentials.complete(c.provider(), c.mode())
                    || (c.provider().equals("payfast") && networks.isBlank())) {
                    throw PaymentException
                        .conflict("Complete the provider credentials and connection details before activation.");
                }
                ProviderConfiguration saved = revision;
                boolean checked = repository.latestCheck(saved.id())
                    .filter(check -> check.successful()
                        && check.checkedAt().isAfter(clock.instant().minus(Duration.ofHours(24))))
                    .isPresent();
                if (!checked) {
                    throw PaymentException
                        .conflict("Run a successful connection check on these saved settings before activation.");
                }
            }
            repository.save(updated);
            java.util.List<String> fields = new java.util.ArrayList<>();
            if (!before.merchantId().equals(credentials.merchantId())) {
                fields.add("merchant ID");
            }
            if (!before.merchantKey().equals(credentials.merchantKey())) {
                fields.add("merchant key");
            }
            if (!before.passphrase().equals(credentials.passphrase())) {
                fields.add("passphrase");
            }
            if (!before.yocoKey().equals(credentials.yocoKey())) {
                fields.add("API key");
            }
            if (!before.webhookSecret().equals(credentials.webhookSecret())) {
                fields.add("webhook secret");
            }
            if (prior == null || !prior.publicUrl().equals(origin)) {
                fields.add("frontend URL");
            }
            if (prior == null || !prior.backendUrl().equals(backendOrigin)) {
                fields.add("backend URL");
            }
            if (prior == null || !prior.ipRanges().equals(networks)) {
                fields.add("notification networks");
            }
            if (current.enabled() != c.enabled()) {
                fields.add("new checkouts");
            }
            if (!current.mode().equals(c.mode())) {
                fields.add("environment");
            }
            if (current.monthly() != c.monthly()) {
                fields.add("monthly giving");
            }
            repository.audit(c.provider(), c.mode(), updated.version(), "SETTINGS_SAVED", reason,
                String.join(", ", fields), c.actorId(), clock.instant());
            return null;
        });
    }
}
