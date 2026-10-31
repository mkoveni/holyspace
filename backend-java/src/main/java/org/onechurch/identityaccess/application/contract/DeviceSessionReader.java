package org.onechurch.identityaccess.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface DeviceSessionReader {
    record Device(UUID id, String deviceName, Instant createdAt, Instant expiresAt) {}
    List<Device> devices(UUID account, Instant now, int limit, int offset);
}
