package org.onechurch.events.application.query.geteventcloseouthistory;
import java.util.UUID;
public record GetEventCloseoutHistoryQuery(UUID occurrenceId, int limit, int offset) {}
