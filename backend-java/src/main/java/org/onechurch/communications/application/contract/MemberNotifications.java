package org.onechurch.communications.application.contract;
import java.util.UUID;
/** Trusted internal capability. Call inside the originating command transaction. No delivery or external I/O. */
public interface MemberNotifications {
    enum Kind {
        VOLUNTEER_INVITATION, DUTY_CHANGED, DUTY_ASSIGNED, EVENT_BOOKING_UPDATED
    }
    void publish(UUID accountId, Kind kind, String sourceKey);
}
