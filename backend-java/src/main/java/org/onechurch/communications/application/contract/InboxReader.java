package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface InboxReader {
    record MemberNotification(UUID id, String topic, String title, String message, String destination,
        Instant createdAt, Instant readAt) {}
    record MemberInbox(List<MemberNotification> items, long unreadCount, boolean hasMore) {}
    record MemberNotificationPreference(String topic, boolean enabled, long version) {}
    MemberInbox list(UUID account, boolean unreadOnly, int limit, int offset);
    List<MemberNotificationPreference> preferences(UUID account);
}
