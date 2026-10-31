package org.onechurch.childrenministry.sessions.domain;

import java.util.Objects;
import java.util.UUID;
public record SessionStaff(UUID sessionId, UUID workerId, StaffRole role, boolean present) {
    public SessionStaff {
        Objects.requireNonNull(role);
    }

}
