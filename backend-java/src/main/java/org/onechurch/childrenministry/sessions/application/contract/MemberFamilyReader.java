package org.onechurch.childrenministry.sessions.application.contract;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public interface MemberFamilyReader {
    record Programme(UUID id, String name, LocalDate validFrom, LocalDate validUntil) {}
    record Session(MinistrySession session, String programme, String className, String room, String location,
        String timeZone) {}
    record FamilyAttendance(UUID id, UUID sessionId, String programme, String className, String room, String location,
        String timeZone, Instant arrivedAt, Instant collectedAt, long version, boolean labelIssued) {}
    List<UUID> children(UUID guardian, Instant at, int limit, int offset);
    boolean authorized(UUID guardian, UUID child, Instant at);
    List<Programme> programmes(UUID child, LocalDate on, int limit, int offset);
    List<Session> sessions(UUID child, Instant from, Instant until, int limit, int offset);
    List<FamilyAttendance> attendance(UUID guardian, UUID child, Instant at, int limit, int offset);
}
