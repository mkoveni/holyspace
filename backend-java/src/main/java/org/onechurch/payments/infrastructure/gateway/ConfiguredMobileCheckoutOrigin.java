package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.MobileCheckoutOrigin;
import org.onechurch.payments.domain.PaymentOrder;
import org.springframework.stereotype.Component;
@Component
public final class ConfiguredMobileCheckoutOrigin implements MobileCheckoutOrigin {
    private final StoredProviderConfigurations configurations;
    public ConfiguredMobileCheckoutOrigin(StoredProviderConfigurations configurations) {
        this.configurations = configurations;
    }
    public String backend(PaymentOrder order) {
        return configurations.forOrder(order).backendBase();
    }
}
