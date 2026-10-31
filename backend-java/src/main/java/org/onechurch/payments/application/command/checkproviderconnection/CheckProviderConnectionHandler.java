package org.onechurch.payments.application.command.checkproviderconnection;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.ProviderConnectionProbe;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
import org.onechurch.payments.domain.ProviderSettings;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class CheckProviderConnectionHandler {
    private final PaymentAccess access;
    private final ProviderConfigurationRepository repository;
    private final PaymentCredentialVault vault;
    private final ProviderConnectionProbe probe;
    private final UnitOfWork tx;
    private final Clock clock;
    public CheckProviderConnectionHandler(PaymentAccess access, ProviderConfigurationRepository repository,
        PaymentCredentialVault vault, ProviderConnectionProbe probe, UnitOfWork tx, Clock clock) {
        this.access = access;
        this.repository = repository;
        this.vault = vault;
        this.probe = probe;
        this.tx = tx;
        this.clock = clock;
    }
    public ConfigurationCheck handle(CheckProviderConnectionCommand c) {
        access.configure(c.actorId());
        ProviderSettings.validate(c.provider(), c.mode());
        ProviderConfiguration configuration = tx.execute(() -> {
            ProviderSettings current = repository.lock(c.provider());
            access.configure(c.actorId());
            current.expected(c.expectedVersion());
            return repository.latest(c.provider(), c.mode())
                .orElseThrow(() -> PaymentException.conflict("Save the connection details first."));
        });
        ProviderCredentials credentials = vault.decrypt(configuration.encryptedCredentials());
        ProviderConnectionProbe.Result result;
        Optional<String> credentialIssue = credentials.validationIssue(c.provider(), c.mode());
        if (credentialIssue.isPresent()) {
            result = new ProviderConnectionProbe.Result(false, credentialIssue.orElseThrow());
        } else if (c.provider().equals("payfast") && configuration.ipRanges().isBlank()) {
            result = new ProviderConnectionProbe.Result(false,
                "Enter the trusted Payfast notification networks in Advanced callback protection, then save and retry.");
        } else {
            try {
                result = probe.check(configuration, credentials);
            } catch (RuntimeException error) {
                result = new ProviderConnectionProbe.Result(false,
                    "Provider connection could not be confirmed. Check the credentials, environment and provider access, then retry.");
            }
        }
        ConfigurationCheck check = new ConfigurationCheck(UUID.randomUUID(), configuration.id(), result.successful(),
            result.message(), c.actorId(), clock.instant());
        return tx.execute(() -> {
            ProviderSettings current = repository.lock(c.provider());
            access.configure(c.actorId());
            current.expected(c.expectedVersion());
            ProviderConfiguration latest = repository.latest(c.provider(), c.mode()).orElseThrow();
            if (!latest.id().equals(configuration.id())) {
                throw PaymentException
                    .conflict("Connection details changed during the check. Test the current saved settings.");
            }
            repository.check(check);
            repository.audit(c.provider(), c.mode(), current.version(), "CONNECTION_CHECKED", check.message(),
                check.successful() ? "successful" : "failed", c.actorId(), clock.instant());
            return check;
        });
    }
}
