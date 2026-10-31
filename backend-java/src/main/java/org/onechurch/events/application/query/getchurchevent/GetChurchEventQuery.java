package org.onechurch.events.application.query.getchurchevent;
import java.util.UUID;
public record GetChurchEventQuery(UUID eventId, int limit, int offset) {}
