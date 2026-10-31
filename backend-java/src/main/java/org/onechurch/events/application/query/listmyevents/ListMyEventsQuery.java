package org.onechurch.events.application.query.listmyevents;
public record ListMyEventsQuery(boolean mine, int limit, int offset) {}
