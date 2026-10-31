package org.onechurch.payments.application.query.listyocowebhooks;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.YocoWebhookManagement;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;
import org.onechurch.payments.application.contract.WebhookOperationReader;
import org.onechurch.payments.application.contract.YocoWebhookReader;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderSettings;
public final class ListYocoWebhooksHandler {
    private final PaymentAccess access;
    private final ProviderConfigurationReader configurations;
    private final PaymentCredentialVault vault;
    private final WebhookOperationReader operations;
    private final YocoWebhookReader webhooks;
    public ListYocoWebhooksHandler(PaymentAccess access, ProviderConfigurationReader configurations,
        PaymentCredentialVault vault, WebhookOperationReader operations, YocoWebhookReader webhooks) {
        this.access = access;
        this.configurations = configurations;
        this.vault = vault;
        this.operations = operations;
        this.webhooks = webhooks;
    }
    public YocoWebhookManagement.Listing handle(ListYocoWebhooksQuery q) {
        access.configure(q.actorId());
        ProviderSettings.validate("yoco", q.mode());
        ProviderConfiguration c = configurations.latest("yoco", q.mode())
            .orElseThrow(() -> PaymentException.conflict("Save the Yoco API key and backend URL first."));
        String key = vault.decrypt(c.encryptedCredentials()).yocoKey();
        if (!key.startsWith("sk_" + q.mode() + "_")) {
            throw PaymentException.conflict("Save a Yoco secret API key for this environment first.");
        }
        return new YocoWebhookManagement.Listing(
            webhooks.list(key).stream().filter(v -> v.mode().equals(q.mode())).toList(),
            operations.pending(q.mode(), q.actorId())
                .stream()
                .map(v -> new YocoWebhookManagement.Pending(v.id(), v.kind(), v.targetId(), v.status(), v.createdAt()))
                .toList());
    }
}
