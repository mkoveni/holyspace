package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
public record TeamPlanningView(List<Session> sessions, long total, Summary summary, String timeZone,
    Instant checkedAt) {
    public record Summary(long sessions, long ready, long attention, long missingLeaders, long shortCapacity,
        long clashes) {}
    public record Session(MinistrySession session, UUID programmeId, String programme, String ministryClass,
        String room, String timeZone, int requiredStaff, int qualifiedStaff, boolean qualifiedLeader,
        int plannedCapacity, boolean ready, boolean editable, List<String> issues, List<Staff> team) {}
    public record Staff(UUID workerId, String name, String role, boolean present, boolean qualified, boolean clash,
        Instant screenedUntil, Instant trainedUntil, List<String> issues) {}
    public record Preview(List<Session> sessions, List<String> blockers, Instant checkedAt) {}
    public record WorkerOption(UUID id, String name, Instant screenedUntil, Instant trainedUntil, boolean revoked) {}
    public record Workers(List<WorkerOption> workers, long total) {}
}
