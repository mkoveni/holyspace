package org.onechurch.childrenministry.participation.application.query.listprogrammechildren;
import java.util.UUID;
public record ListProgrammeChildrenQuery(UUID programmeId, String search, String status, int limit, int offset) {}
