package org.onechurch.childrenministry.sessions.application.query.getsessionday;
import org.onechurch.childrenministry.sessions.application.AttendanceView;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
public record SessionDayPage(List<Child> children, long total, Instant checkedAt) {
    public record Child(UUID childId, String name, AttendanceView attendance, boolean ready, List<String> issues) {}
}
