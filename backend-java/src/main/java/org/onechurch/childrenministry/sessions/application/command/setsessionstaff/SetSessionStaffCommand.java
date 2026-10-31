package org.onechurch.childrenministry.sessions.application.command.setsessionstaff;

import org.onechurch.childrenministry.sessions.domain.StaffRole;

import java.util.UUID;
public record SetSessionStaffCommand(UUID actorId, UUID sessionId, long expectedVersion, UUID workerId, StaffRole role,
    boolean present) {}
