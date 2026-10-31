package org.onechurch.payments.application.query.getpaymentsettings;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;

import java.util.List;
public final class GetPaymentSettingsHandler {
    public record Result(boolean encryptionReady, List<ProviderConfigurationReader.Provider> providers,
        List<ProviderConfigurationReader.Change> history) {}
    private final PaymentAccess access;
    private final ProviderConfigurationReader reader;
    private final PaymentCredentialVault vault;
    public GetPaymentSettingsHandler(PaymentAccess access, ProviderConfigurationReader reader,
        PaymentCredentialVault vault) {
        this.access = access;
        this.reader = reader;
        this.vault = vault;
    }
    public Result handle(GetPaymentSettingsQuery q) {
        access.configureRead();
        return new Result(vault.ready(), reader.settings(), reader.history());
    }
}
