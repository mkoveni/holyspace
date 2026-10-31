package org.onechurch.childrenministry.participation.application.query.listenrolments;

import java.util.UUID;
public record ListEnrolmentsQuery(UUID childId, int limit, int offset) {}
