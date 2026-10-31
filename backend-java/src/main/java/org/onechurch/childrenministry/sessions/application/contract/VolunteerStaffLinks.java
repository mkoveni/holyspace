package org.onechurch.childrenministry.sessions.application.contract;

import java.util.Optional;
import java.util.UUID;

public interface VolunteerStaffLinks {
    record Link(UUID assignmentId, String role) {}
    Optional<Link> find(UUID sessionId, UUID workerId);
}
