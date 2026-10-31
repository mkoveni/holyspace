package org.onechurch.payments.infrastructure;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;
@Component
public final class PaymentReceiptTemplatePolicy implements TemplatePublicationPolicy {
    public Optional<String> violation(String key, String subject, String html, List<String> variables) {
        if (!"payments.receipt".equals(key)) {
            return Optional.empty();
        }
        if (!variables.containsAll(List.of("name", "amount", "date", "provider", "reference"))
            || !html.toLowerCase(java.util.Locale.ROOT).contains("not a tax certificate")) {
            return Optional.of(
                "Payment confirmations need name, amount, date, provider and reference variables and the wording 'not a tax certificate'.");
        }
        return Optional.empty();
    }
}
