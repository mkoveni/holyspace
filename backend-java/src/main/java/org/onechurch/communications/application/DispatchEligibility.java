package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySources;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.EmailDelivery;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
public final class DispatchEligibility {
    private final DeliverySources sources;
    private final List<DeliveryEligibility> policies;
    public DispatchEligibility(DeliverySources sources, List<DeliveryEligibility> policies) {
        this.sources = sources;
        this.policies = List.copyOf(policies);
    }
    public void authorize(EmailDelivery delivery, UUID actor, String operation) {
        String source = sources.context(delivery.id());
        DeliveryEligibility policy = policies.stream()
            .filter(p -> p.sourceContext().equals(source))
            .findFirst()
            .orElseThrow(() -> CommunicationException.forbidden());
        policy.authorize(DeliveryMapping.snapshot(delivery), actor, operation);
    }
    public void require(EmailDelivery delivery, Instant at) {
        String source = sources.context(delivery.id());
        if (policies.stream().filter(p -> p.sourceContext().equals(source)).count() != 1 || policies.stream()
            .filter(p -> p.sourceContext().equals(source))
            .noneMatch(p -> p.eligible(DeliveryMapping.snapshot(delivery), at))) {
            throw CommunicationException
                .conflict("Sender, recipient or source eligibility changed. Review before retrying.");
        }
    }
}
