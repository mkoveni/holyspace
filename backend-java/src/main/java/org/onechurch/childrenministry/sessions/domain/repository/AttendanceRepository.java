package org.onechurch.childrenministry.sessions.domain.repository;

import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public interface AttendanceRepository {
    Optional<Attendance> attendanceForUpdate(UUID id);
    void insertAttendance(Attendance value);
    void updateAttendance(Attendance value);
    void insertAttendanceSegment(AttendanceSegment value);
    void insertAttendanceEvent(AttendanceEvent value);
    void closeSegment(UUID attendanceId, Instant at);
}
