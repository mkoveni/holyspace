package org.onechurch.communities.infrastructure;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.events.application.contract.OccurrenceScheduleListener;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;
@Component
public class CommunityScheduleListener implements OccurrenceScheduleListener {
    private final CommunityWriter writer;
    public CommunityScheduleListener(CommunityWriter writer) {
        this.writer = writer;
    }
    public void changed(UUID occurrence, Instant startsAt) {
        writer.scheduleChanged(occurrence, startsAt);
    }
}
