package org.onechurch.childrenministry.reporting.application;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public record AttendanceReport(List<Visit> visits, Summary summary, String timeZone, Instant checkedAt) {
    public record Summary(long visits, long children, long attending, long collected, long transferred,
        long supervisorCollections) {}
    public record Visit(UUID id, UUID attendanceId, UUID childId, String childName, UUID sessionId, UUID programmeId,
        String programme, String ministryClass, String room, String location, String timeZone, Instant arrivedAt,
        Instant leftAt, String outcome, boolean supervisorCollection) {
        public Visit named(String name) {
            return new Visit(id, attendanceId, childId, name, sessionId, programmeId, programme, ministryClass, room,
                location, timeZone, arrivedAt, leftAt, outcome, supervisorCollection);
        }
    }
}
