package org.onechurch.childrenministry.participation.application.query.listclassassignments;

import java.util.UUID;
public record ListClassAssignmentsQuery(UUID childId, int limit, int offset) {}
