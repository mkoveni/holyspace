package org.onechurch.childrenministry.sessions.application.contract;

import java.util.UUID;

public interface VolunteerStaffLinkWriter {
    void save(UUID sessionId, UUID workerId, UUID assignmentId, String role);
}
