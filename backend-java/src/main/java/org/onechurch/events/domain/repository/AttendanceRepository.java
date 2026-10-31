package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.Attendance;

import java.time.Instant;
import java.util.UUID;
public interface AttendanceRepository {
    void insert(Attendance attendance);
    void save(Attendance attendance);
    void history(UUID attendance, UUID actor, String action, String reason, Instant now);
}
