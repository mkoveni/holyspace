package org.onechurch.childrenministry.reporting.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface MinistryOverviewReader {
    record Session(UUID id, String programme, String ministryClass, String room, Instant startsAt, String status) {}
    record Upcoming(long total, List<Session> sessions) {}
    Upcoming upcoming(Instant from, Instant until);
}
