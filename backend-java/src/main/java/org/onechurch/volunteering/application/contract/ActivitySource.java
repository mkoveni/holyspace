package org.onechurch.volunteering.application.contract;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

/** Published activity metadata. Source aggregates and persistence remain private. */
public interface ActivitySource {
    record Activity(UUID id, String label, Instant startsAt, Instant endsAt, boolean active, String scheduleRevision,
        String venueLabel) {
        public Activity(UUID id, String label, Instant startsAt, Instant endsAt, boolean active) {
            this(id, label, startsAt, endsAt, active, null, null);
        }
    }
    String kind();
    Optional<Activity> find(UUID id);
    List<Activity> upcoming(Instant from, Instant until);
    default List<Activity> upcoming(Instant from, Instant until, int limit, int offset) {
        return upcoming(from, until).stream().skip(offset).limit(limit).toList();
    }
}
