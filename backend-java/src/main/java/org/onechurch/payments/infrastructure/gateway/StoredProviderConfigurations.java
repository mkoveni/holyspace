package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.LegacyPaymentConfiguration;
import org.onechurch.payments.application.contract.PaymentConfigurationBinding;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
import org.onechurch.payments.domain.ProviderSettings;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.springframework.stereotype.Component;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import java.util.UUID;
@Component
public final class StoredProviderConfigurations implements PaymentConfigurationBinding, LegacyPaymentConfiguration {
    private final ProviderConfigurationReader reader;
    private final ProviderConfigurationRepository repository;
    private final PaymentCredentialVault vault;
    private final PaymentSettings bootstrap;
    private final PaymentRepository payments;
    private final JsonMapper json = JsonMapper.builder().build();
    public StoredProviderConfigurations(ProviderConfigurationReader reader, ProviderConfigurationRepository repository,
        PaymentCredentialVault vault, PaymentSettings bootstrap, PaymentRepository payments) {
        this.reader = reader;
        this.repository = repository;
        this.vault = vault;
        this.bootstrap = bootstrap;
        this.payments = payments;
    }
    public record Resolved(PaymentSettings settings, boolean available, boolean monthly) {}
    private PaymentSettings materialize(ProviderConfiguration revision) {
        ProviderCredentials c = vault.decrypt(revision.encryptedCredentials());
        return new PaymentSettings(revision.publicUrl(), revision.mode(), c.merchantId(), c.merchantKey(),
            c.passphrase(), revision.ipRanges(), revision.mode(), c.yocoKey(), c.webhookSecret(), bootstrap.vaultKey,
            revision.backendUrl());
    }
    public Resolved current(String provider) {
        ProviderSettings head =
            reader.settings(provider).orElseThrow(() -> PaymentException.invalid("Choose a supported provider."));
        if (!head.managed()) {
            return new Resolved(bootstrap, bootstrap.available(provider), provider.equals("payfast"));
        }
        ProviderConfiguration latest = reader.latest(provider, head.mode()).orElse(null);
        if (latest == null || !vault.ready()) {
            return new Resolved(
                new PaymentSettings("", head.mode(), "", "", "", "", head.mode(), "", "", bootstrap.vaultKey, ""),
                false, head.monthly());
        }
        PaymentSettings settings = materialize(latest);
        return new Resolved(settings, head.enabled() && settings.available(provider)
            && (!provider.equals("yoco") || !repository.activeWebhookRemoval(head.mode())), head.monthly());
    }
    @Override
    public UUID bind(String provider, String mode, boolean recurring) {
        ProviderSettings head = repository.lock(provider);
        if (!head.managed()) {
            return null;
        }
        if (provider.equals("yoco") && repository.activeWebhookRemoval(mode)) {
            throw PaymentException.conflict("A webhook removal needs review before new checkouts can start.");
        }
        if (!head.enabled() || !head.mode().equals(mode) || recurring && !head.monthly()) {
            throw PaymentException
                .conflict("Payment settings changed or this checkout option is disabled. Refresh the provider list.");
        }
        return repository.latest(provider, mode)
            .orElseThrow(() -> PaymentException.conflict("Configure this payment environment first."))
            .id();
    }
    public PaymentSettings forOrder(PaymentOrder order) {
        if (order.configurationId() == null) {
            String mode = order.provider().equals("payfast") ? bootstrap.payfastMode : bootstrap.yocoMode;
            if (!mode.equals(order.mode()) || !bootstrap.available(order.provider())) {
                throw PaymentException.unavailable(
                    "The deployment credentials for this older payment are unavailable. Restore its original provider credentials.");
            }
            return bootstrap;
        }
        ProviderConfiguration revision = reader.revision(order.configurationId())
            .filter(c -> c.provider().equals(order.provider()) && c.mode().equals(order.mode()))
            .orElseThrow(() -> PaymentException.unavailable("The original payment configuration is unavailable."));
        return materialize(revision);
    }
    /**
     * Untrusted hints select a candidate only; the gateway must authenticate the unchanged raw body before any write.
     */
    public PaymentSettings forNotification(String provider, String body) {
        try {
            if (provider.equals("yoco")) {
                JsonNode payload = json.readTree(body).path("payload");
                String mode = payload.path("mode").asText(),
                    checkout = payload.path("metadata").path("checkoutId").asText("");
                UUID id = payments.lookup(provider, mode, checkout, null).orElse(null);
                if (id == null) {
                    id = uuid(payload.path("metadata").path("orderId").asText(""));
                }
                if (id != null) {
                    PaymentOrder order = payments.find(id).filter(o -> o.provider().equals(provider)).orElse(null);
                    if (order != null) {
                        return forOrder(order);
                    }
                }
            } else if (provider.equals("payfast")) {
                Map<String, String> fields = new java.util.HashMap<>();
                for (String pair : body.split("&")) {
                    String[] parts = pair.split("=", 2);
                    if (parts.length == 2) {
                        fields.put(URLDecoder.decode(parts[0], StandardCharsets.UTF_8),
                            URLDecoder.decode(parts[1], StandardCharsets.UTF_8));
                    }
                }
                UUID id = uuid(fields.get("m_payment_id"));
                if ((id == null || payments.find(id).filter(o -> o.provider().equals(provider)).isEmpty())
                    && fields.get("token") != null) {
                    String hash = org.onechurch.payments.application.PaymentHash.of(fields.get("token"));
                    id = payments.lookup(provider, "live", null, hash)
                        .or(() -> payments.lookup(provider, "test", null, hash))
                        .orElse(null);
                }
                if (id != null) {
                    PaymentOrder order = payments.find(id).filter(o -> o.provider().equals(provider)).orElse(null);
                    if (order != null) {
                        return forOrder(order);
                    }
                }
            }
        } catch (PaymentException error) {
            throw error;
        } catch (RuntimeException error) {
            throw PaymentException.invalid("Invalid payment notification.");
        }
        return current(provider).settings();
    }
    private static UUID uuid(String value) {
        try {
            return value == null || value.isBlank() ? null : UUID.fromString(value);
        } catch (IllegalArgumentException error) {
            return null;
        }
    }
    @Override
    public ProviderCredentials credentials(String provider, String mode) {
        if (provider.equals("payfast") && bootstrap.payfastMode.equals(mode)) {
            return new ProviderCredentials(bootstrap.merchantId, bootstrap.merchantKey, bootstrap.passphrase, null,
                null);
        }
        if (provider.equals("yoco") && bootstrap.yocoMode.equals(mode)) {
            return new ProviderCredentials(null, null, null, bootstrap.yocoKey, bootstrap.yocoWebhookSecret);
        }
        return new ProviderCredentials(null, null, null, null, null);
    }
    @Override
    public String publicUrl() {
        return bootstrap.publicUrl;
    }
    @Override
    public String ipRanges() {
        return bootstrap.ipRanges;
    }
}
