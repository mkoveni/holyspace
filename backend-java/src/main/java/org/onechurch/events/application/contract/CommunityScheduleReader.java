package org.onechurch.events.application.contract;
import java.util.List;
import java.util.UUID;
/** Read-only batch projection of linked community schedules. Does not schedule or lock occurrences. */
public interface CommunityScheduleReader {
    List<CommunityCalendar.Date> dates(List<UUID> occurrences);
}
