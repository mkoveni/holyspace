package org.onechurch.events.application.query.geteventoccurrencehistory;
import java.util.UUID;
public record GetEventOccurrenceHistoryQuery(UUID occurrenceId, int limit, int offset) {}
