package org.onechurch.events.application.query.geteventregistrationhistory;
import java.util.UUID;
public record GetEventRegistrationHistoryQuery(UUID registrationId, int limit, int offset) {}
