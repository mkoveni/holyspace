package org.onechurch.payments.domain.repository;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderSettings;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public interface ProviderConfigurationRepository {
    boolean activeWebhookRemoval(String mode);
    Optional<ProviderConfiguration> revision(UUID id);
    ProviderSettings lock(String provider);
    Optional<ProviderConfiguration> latest(String provider, String mode);
    Optional<ConfigurationCheck> latestCheck(UUID revision);
    void insert(ProviderConfiguration configuration);
    void save(ProviderSettings settings);
    void check(ConfigurationCheck check);
    void audit(String provider, String mode, long version, String action, String note, String changedFields, UUID actor,
        Instant now);
}
