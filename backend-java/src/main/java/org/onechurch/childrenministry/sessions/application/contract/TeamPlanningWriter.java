package org.onechurch.childrenministry.sessions.application.contract;
import java.util.UUID;
public interface TeamPlanningWriter {
    void removeAssignment(UUID sessionId, UUID workerId);
}
