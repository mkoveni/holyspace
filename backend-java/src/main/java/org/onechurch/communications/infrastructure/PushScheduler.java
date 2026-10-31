package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.PushDeliveryProcessor;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;
@Component
public final class PushScheduler {
    private final PushDeliveryProcessor processor;
    public PushScheduler(PushDeliveryProcessor processor) {
        this.processor = processor;
    }
    @Scheduled(fixedDelayString = "${PUSH_POLL_MS:15000}", initialDelayString = "${PUSH_POLL_MS:15000}")
    public void dispatch() {
        processor.runDue();
    }
}
