package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
public interface ProviderConnectionProbe {
    record Result(boolean successful, String message) {}
    void validateNetworks(String ipRanges);
    Result check(ProviderConfiguration configuration, ProviderCredentials credentials);
}
