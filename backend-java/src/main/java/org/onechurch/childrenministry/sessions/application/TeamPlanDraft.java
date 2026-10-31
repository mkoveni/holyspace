package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.domain.StaffRole;

import java.util.List;
import java.util.UUID;
public record TeamPlanDraft(List<Target> targets, UUID workerId, UUID replaceWorkerId, StaffRole role) {
    public record Target(UUID sessionId, long version) {}
}
