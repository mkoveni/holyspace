package org.onechurch.childrenministry.participation.application.command.endenrolment;

import java.util.UUID;
public record EndEnrolmentCommand(UUID actorId, UUID enrolmentId, long expectedVersion) {}
