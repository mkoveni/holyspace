package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.DeliveryLifecycle;
import org.onechurch.communications.application.DeliveryProcessor;
import org.onechurch.communications.application.DispatchEligibility;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySources;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.communications.domain.repository.DeliveryRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.scheduling.annotation.EnableScheduling;

import java.time.Clock;
import java.util.List;
@EnableScheduling
@Configuration
public class CommunicationsConfiguration {
    @Bean
    DispatchEligibility dispatchEligibility(DeliverySources sources, List<DeliveryEligibility> policies) {
        return new DispatchEligibility(sources, policies);
    }
    @Bean
    EmailDeliveries emailDeliveries(@Qualifier("communicationDeliveryStore") DeliveryReader reader,
        DeliveryRepository store, DeliverySources sources, DispatchEligibility policy, UnitOfWork tx,
        CommunicationAudit audit, Clock clock, EmailTransport transport) {
        return new DeliveryLifecycle(reader, store, sources, policy, tx, audit, clock, transport);
    }
    @Bean
    DeliveryProcessor deliveryProcessor(@Qualifier("communicationDeliveryStore") DeliveryReader reader,
        DeliveryRepository store, DispatchEligibility policy, EmailTransport transport, UnitOfWork tx,
        CommunicationAudit audit, Clock clock) {
        return new DeliveryProcessor(reader, store, policy, transport, tx, audit, clock);
    }
}
