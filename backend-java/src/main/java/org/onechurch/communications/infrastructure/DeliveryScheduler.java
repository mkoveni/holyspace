package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.DeliveryProcessor;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;
@Component
public final class DeliveryScheduler {
    private final DeliveryProcessor processor;
    public DeliveryScheduler(DeliveryProcessor processor) {
        this.processor = processor;
    }
    @Scheduled(fixedDelayString = "${onechurch.email.poll-ms:15000}")
    public void dispatch() {
        processor.runDue();
    }
}
