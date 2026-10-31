package org.onechurch.events.application.contract;
import java.time.Instant;
import java.util.UUID;
/** Synchronous source projections participate in the scheduling transaction. */
public interface OccurrenceScheduleListener {
    void changed(UUID occurrence, Instant startsAt);
}
