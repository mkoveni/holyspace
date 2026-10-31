package org.onechurch.events.application.query.listchurchevents;

public record ListChurchEventsQuery(String search, boolean includeArchived, int limit, int offset) {}
