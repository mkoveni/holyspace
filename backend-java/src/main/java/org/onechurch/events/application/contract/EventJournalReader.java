package org.onechurch.events.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface EventJournalReader {
    record Request(UUID id, String kind, String hash, UUID targetId, UUID actorId, Instant createdAt) {}
    record RegistrationEntry(UUID id, String action, String reason, Instant occurredAt) {}
    record OccurrenceEntry(UUID id, String action, String reason, Instant startsAt, Instant endsAt, UUID venueId,
        String venueName, String venueAddress, String status, long version, long scheduleRevision,
        Instant occurredAt) {}
    Optional<Request> request(UUID id);
    List<RegistrationEntry> registrationHistory(UUID id, int limit, int offset);
    List<OccurrenceEntry> occurrenceHistory(UUID id, int limit, int offset);
}
