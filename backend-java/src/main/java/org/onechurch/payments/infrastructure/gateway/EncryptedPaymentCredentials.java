package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.ProviderCredentials;
import org.springframework.stereotype.Component;

import tools.jackson.databind.json.JsonMapper;
@Component
public final class EncryptedPaymentCredentials implements PaymentCredentialVault {
    private final PaymentSettings settings;
    private final SubscriptionVault vault;
    private final JsonMapper json = JsonMapper.builder().build();
    public EncryptedPaymentCredentials(PaymentSettings settings, SubscriptionVault vault) {
        this.settings = settings;
        this.vault = vault;
    }
    @Override
    public boolean ready() {
        return settings.validVault();
    }
    @Override
    public String encrypt(ProviderCredentials credentials) {
        return vault.encrypt(json.writeValueAsString(credentials));
    }
    @Override
    public ProviderCredentials decrypt(String encrypted) {
        return json.readValue(vault.decrypt(encrypted), ProviderCredentials.class);
    }
}
