package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.ProviderCredentials;
public interface PaymentCredentialVault {
    boolean ready();
    String encrypt(ProviderCredentials credentials);
    ProviderCredentials decrypt(String encrypted);
}
