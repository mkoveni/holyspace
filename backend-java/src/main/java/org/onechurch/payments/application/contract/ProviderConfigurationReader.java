package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderSettings;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface ProviderConfigurationReader {
    record Environment(String mode, UUID revisionId, String publicUrl, String ipRanges, boolean merchantIdSet,
        boolean merchantKeySet, boolean passphraseSet, boolean yocoKeySet, boolean webhookSecretSet, boolean complete,
        ConfigurationCheck connection, String backendUrl) {}
    record Provider(ProviderSettings settings, List<Environment> environments) {}
    record Change(String provider, String mode, long version, String action, String note, String changedFields,
        UUID actorId, String actorName, Instant occurredAt) {}
    List<Provider> settings();
    List<Change> history();
    Optional<ProviderSettings> settings(String provider);
    Optional<ProviderConfiguration> revision(UUID id);
    Optional<ProviderConfiguration> latest(String provider, String mode);
    Optional<ConfigurationCheck> latestCheck(UUID revision);
}
