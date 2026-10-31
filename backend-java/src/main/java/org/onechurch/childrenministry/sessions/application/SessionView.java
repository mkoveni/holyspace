package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.StaffRole;

import java.util.List;
import java.util.UUID;
public record SessionView(MinistrySession session, String className, String roomName, String timeZone,
    int effectiveCapacity, int occupancy, boolean staffingNeedsReview, List<Staff> staff) {
    public record Staff(UUID personId, String name, StaffRole role, boolean present, List<String> issues) {}
}
