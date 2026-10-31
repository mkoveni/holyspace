package org.onechurch.communications.application.command.registermypushdevice;
import java.util.UUID;
public record RegisterMyPushDeviceCommand(UUID deviceId, String installationSecret, String token, String platform,
    UUID projectId) {
    @Override
    public String toString() {
        return "RegisterMyPushDeviceCommand[redacted]";
    }
}
