package org.onechurch.events.application.query.listeventvenues;

public record ListEventVenuesQuery(String search, boolean includeArchived, int limit, int offset) {}
