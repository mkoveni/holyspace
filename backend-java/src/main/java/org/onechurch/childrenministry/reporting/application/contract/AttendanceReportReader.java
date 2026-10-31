package org.onechurch.childrenministry.reporting.application.contract;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;

import java.time.Instant;
import java.util.Collection;
import java.util.UUID;
public interface AttendanceReportReader {
    record Criteria(Instant from, Instant until, UUID programmeId, UUID locationId, UUID childId,
        Collection<UUID> matchingIds, String outcome, UUID attendanceId) {}
    AttendanceReport read(Criteria criteria, int limit, int offset);
}
