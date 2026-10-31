package org.onechurch.payments.application.command.processnotification;
import java.util.Map;
public record ProcessNotificationCommand(String provider, String body, Map<String, String> headers, String ip) {
    @Override
    public String toString() {
        return "ProcessNotificationCommand[provider=" + provider + "]";
    }
}
