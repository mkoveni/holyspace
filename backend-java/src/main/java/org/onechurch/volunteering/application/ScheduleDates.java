package org.onechurch.volunteering.application;

import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.VolunteerException;

import java.nio.charset.StandardCharsets;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.ZoneId;
import java.util.List;
import java.util.Objects;
import java.util.UUID;

public final class ScheduleDates {
    private ScheduleDates() {
    }
    public static List<RosterPlanning.Draft> preview(UUID request, UUID team, UUID role, List<LocalDate> dates,
        LocalTime start, LocalTime end, String timeZone, int positions) {
        ServingRules.id(request);
        ServingRules.id(team);
        ServingRules.id(role);
        if (dates == null || dates.isEmpty() || dates.size() > 25 || dates.stream().anyMatch(Objects::isNull)
            || dates.stream().distinct().count() != dates.size() || start == null || end == null || start.equals(end)
            || positions < 1 || positions > 100) {
            throw VolunteerException.invalid("Choose 1–25 unique dates, times and 1–100 positions.");
        }
        ZoneId zone = ServingRules.zone(timeZone);
        return dates.stream().sorted().map(day -> {
            LocalDateTime from = day.atTime(start), until = (end.isAfter(start) ? day : day.plusDays(1)).atTime(end);
            if (zone.getRules().getValidOffsets(from).size() != 1
                || zone.getRules().getValidOffsets(until).size() != 1) {
                throw VolunteerException.invalid(
                    "A selected local time is ambiguous or does not exist because of daylight saving. Choose a different time.");
            }
            UUID key = UUID.nameUUIDFromBytes((request + ":" + day).getBytes(StandardCharsets.UTF_8));
            return new RosterPlanning.Draft(key, team, role, "STANDALONE", null, from.atZone(zone).toInstant(),
                until.atZone(zone).toInstant(), positions);
        }).toList();
    }
}
