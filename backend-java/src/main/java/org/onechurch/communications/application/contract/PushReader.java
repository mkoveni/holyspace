package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PushReader {
    record PushDeviceView(UUID id, String platform, String status, Instant expiresAt, Instant updatedAt,
        boolean currentSession) {}
    record PushPreference(String topic, boolean enabled, long version) {}
    record MemberPushSettings(boolean available, UUID projectId, List<PushPreference> preferences,
        List<PushDeviceView> devices) {}
    List<PushDeviceView> devices(UUID account, UUID session);
    List<PushPreference> preferences(UUID account);
    Optional<InboxReader.MemberNotification> notification(UUID account, UUID id);
}
