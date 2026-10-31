package org.onechurch.childrenministry.sessions.application.query.getteamplanning;
import java.time.LocalDate;
import java.util.UUID;
public record GetTeamPlanningQuery(LocalDate from, LocalDate until, UUID programmeId, UUID locationId, String search,
    String state, int limit, int offset) {}
