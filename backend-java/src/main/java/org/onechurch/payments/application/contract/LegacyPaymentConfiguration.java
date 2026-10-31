package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.ProviderCredentials;
public interface LegacyPaymentConfiguration {
    ProviderCredentials credentials(String provider, String mode);
    String publicUrl();
    String ipRanges();
}
