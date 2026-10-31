package org.onechurch.childrenministry.sessions.application.contract;
import org.onechurch.childrenministry.sessions.domain.Attendance;

import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public interface SessionDayReader {
    record ExpectedPage(List<UUID> childIds, long total) {}
    record AttendancePage(List<Attendance> attendance, long total) {}
    ExpectedPage expected(UUID classId, LocalDate on, Collection<UUID> matchingIds, int limit, int offset);
    AttendancePage attendance(UUID sessionId, boolean collected, Collection<UUID> matchingIds, int limit, int offset);
    Map<UUID, Attendance> current(UUID sessionId, Collection<UUID> childIds);
}
