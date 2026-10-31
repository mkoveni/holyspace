package org.onechurch.events.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.ZoneId;
import java.util.List;
import java.util.stream.IntStream;
public final class EventSchedule {
    private EventSchedule() {
    }
    public record Window(LocalDate originDate, Instant startsAt, Instant endsAt) {}
    public static Window window(LocalDate date, LocalTime start, LocalTime end, int offset, String timeZone) {
        if (date == null || date.getYear() < 1900 || date.getYear() > 2200 || start == null || end == null || offset < 0
            || offset > 7) {
            throw EventException.invalid("Choose a date, times and end day offset of 0–7.");
        }
        ZoneId zone = EventRules.zone(timeZone);
        LocalDateTime from = date.atTime(start), until = date.plusDays(offset).atTime(end);
        if (zone.getRules().getValidOffsets(from).size() != 1 || zone.getRules().getValidOffsets(until).size() != 1) {
            throw EventException.invalid(
                "A local time is ambiguous or does not exist because of daylight saving. Choose a different time.");
        }
        Instant starts = EventRules.instant(from.atZone(zone).toInstant()),
            ends = EventRules.instant(until.atZone(zone).toInstant());
        EventRules.period(starts, ends);
        return new Window(date, starts, ends);
    }
    public static List<Window> preview(EventSeries series) {
        return IntStream.range(0, series.count()).mapToObj(index -> {
            long step = (long) index * series.interval();
            LocalDate date = switch (series.frequency()) {
                case "ONCE" -> series.firstDate();
                case "DAILY" -> series.firstDate().plusDays(step);
                case "WEEKLY" -> series.firstDate().plusWeeks(step);
                case "MONTHLY" -> series.firstDate().plusMonths(step);
                default -> throw EventException.invalid("Choose a recurrence.");
            };
            if (date.isAfter(series.firstDate().plusYears(2))) {
                throw EventException.invalid("The preview must fit within two years. Use a shorter finite series.");
            }
            if (series.frequency().equals("MONTHLY") && date.getDayOfMonth() != series.firstDate().getDayOfMonth()) {
                throw EventException.invalid(
                    "A monthly date is missing in one of the months. Choose a day from 1 to 28, or plan individual dates.");
            }
            return window(date, series.startsAt(), series.endsAt(), series.endDayOffset(), series.timeZone());
        }).toList();
    }
}
