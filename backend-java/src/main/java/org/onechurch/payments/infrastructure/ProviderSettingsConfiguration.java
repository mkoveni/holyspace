package org.onechurch.payments.infrastructure;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.command.checkproviderconnection.CheckProviderConnectionHandler;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsHandler;
import org.onechurch.payments.application.contract.LegacyPaymentConfiguration;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;
import org.onechurch.payments.application.contract.ProviderConnectionProbe;
import org.onechurch.payments.application.query.getpaymentsettings.GetPaymentSettingsHandler;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class ProviderSettingsConfiguration {
    @Bean
    SaveProviderSettingsHandler saveProviderSettingsHandler(PaymentAccess access,
        ProviderConfigurationRepository repository, PaymentCredentialVault vault, LegacyPaymentConfiguration legacy,
        ProviderConnectionProbe probe, UnitOfWork tx, Clock clock) {
        return new SaveProviderSettingsHandler(access, repository, vault, legacy, probe, tx, clock);
    }
    @Bean
    CheckProviderConnectionHandler checkProviderConnectionHandler(PaymentAccess access,
        ProviderConfigurationRepository repository, PaymentCredentialVault vault, ProviderConnectionProbe probe,
        UnitOfWork tx, Clock clock) {
        return new CheckProviderConnectionHandler(access, repository, vault, probe, tx, clock);
    }
    @Bean
    GetPaymentSettingsHandler getPaymentSettingsHandler(PaymentAccess access, ProviderConfigurationReader reader,
        PaymentCredentialVault vault) {
        return new GetPaymentSettingsHandler(access, reader, vault);
    }
}
