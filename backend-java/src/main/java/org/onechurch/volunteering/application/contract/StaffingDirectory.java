package org.onechurch.volunteering.application.contract;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

/** Published readiness for one exact assignment, source and duty interval. */
public interface StaffingDirectory {
    record Assessment(UUID assignmentId, UUID personId, String name, String responsibility, boolean ready,
        List<String> issues) {}
    Assessment assess(UUID assignmentId, UUID sessionId, String responsibility, Instant from, Instant until);
    List<Assessment> candidates(UUID sessionId, Instant from, Instant until);
}
