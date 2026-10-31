package org.onechurch.communications.application.contract;
import java.util.UUID;
public interface PushTransport {
    record Result(String state, String ticketId, String errorCode) {}
    Result send(String token, UUID notificationId);
    Result receipt(String ticketId);
}
