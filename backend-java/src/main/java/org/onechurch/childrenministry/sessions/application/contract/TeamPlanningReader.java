package org.onechurch.childrenministry.sessions.application.contract;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.Worker;

import java.time.Instant;
import java.util.Collection;
import java.util.List;
import java.util.Set;
import java.util.UUID;
public interface TeamPlanningReader {
    record Session(MinistrySession value, UUID programmeId, String programme, String ministryClass, String room,
        UUID locationId, String timeZone, int roomCapacity, int minimumStaff, int childrenPerStaff) {}
    record Booking(UUID sessionId, UUID workerId, Instant startsAt, Instant endsAt) {}
    record Facts(List<SessionStaff> staff, List<Worker> workers, List<Booking> bookings) {}
    record Workers(List<Worker> values, long total) {}
    List<Session> upcoming(Instant from, Instant until, UUID programmeId, UUID locationId, Instant now);
    List<Session> sessions(Collection<UUID> ids);
    Facts facts(List<Session> sessions, Set<UUID> extraWorkers);
    Workers workers(Collection<UUID> matchingIds, int limit, int offset);
}
