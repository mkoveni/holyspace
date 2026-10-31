package org.onechurch.childrenministry.sessions.application.query.getexpectedchildren;
import java.util.UUID;
public record ExpectedChild(UUID childId, String name, UUID enrolmentId, UUID assignmentId) {}
