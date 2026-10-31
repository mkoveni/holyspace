package org.onechurch.payments.application.contract;
import java.util.UUID;
public interface PaymentConfigurationBinding {
    /** Called in the order transaction; pins the selected immutable revision and rechecks checkout controls. */
    UUID bind(String provider, String mode, boolean recurring);
}
