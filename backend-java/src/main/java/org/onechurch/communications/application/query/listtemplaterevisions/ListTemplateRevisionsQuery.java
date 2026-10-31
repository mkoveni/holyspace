package org.onechurch.communications.application.query.listtemplaterevisions;
import java.util.UUID;
public record ListTemplateRevisionsQuery(UUID id, int limit, int offset) {}
