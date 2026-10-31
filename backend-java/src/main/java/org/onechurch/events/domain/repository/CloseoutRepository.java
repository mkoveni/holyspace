package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.OccurrenceCloseout;
public interface CloseoutRepository {
    void save(OccurrenceCloseout closeout, long attended, long absent, long waitlisted, long unresolvedNotices);
}
