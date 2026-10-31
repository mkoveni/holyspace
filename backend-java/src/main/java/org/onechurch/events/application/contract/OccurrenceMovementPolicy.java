package org.onechurch.events.application.contract;
import java.util.UUID;
/** Source consumers protect historical participation before a scheduling move. Must be read-only. */
public interface OccurrenceMovementPolicy {
    boolean movable(UUID occurrence);
}
