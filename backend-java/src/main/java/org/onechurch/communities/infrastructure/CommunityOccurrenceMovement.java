package org.onechurch.communities.infrastructure;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.events.application.contract.OccurrenceMovementPolicy;
import org.springframework.stereotype.Component;

import java.util.UUID;
@Component
public class CommunityOccurrenceMovement implements OccurrenceMovementPolicy {
    private final CommunityReader reader;
    public CommunityOccurrenceMovement(CommunityReader reader) {
        this.reader = reader;
    }
    public boolean movable(UUID id) {
        return !reader.recordedOccurrence(id);
    }
}
