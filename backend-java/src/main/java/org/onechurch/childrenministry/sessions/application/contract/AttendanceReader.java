package org.onechurch.childrenministry.sessions.application.contract;

import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface AttendanceReader {
    Optional<Attendance> attendance(UUID id);
    List<Attendance> roster(UUID sessionId, boolean openOnly, int limit, int offset);
    int occupancy(UUID sessionId);
    Optional<Attendance> arrival(UUID actorId, UUID requestId);
    List<AttendanceEvent> events(UUID attendanceId, int limit, int offset);
    List<AttendanceSegment> segments(UUID attendanceId);
    List<Attendance> childAttendance(UUID childId, int limit, int offset);
}
