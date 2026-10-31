package org.onechurch.events.application.query.listeventregistrations;
import java.util.UUID;
public record ListEventRegistrationsQuery(UUID occurrenceId, String status, int limit, int offset) {}
