package org.onechurch.volunteering.application.contract;
import java.util.UUID;
/** Published operational totals; no volunteer identities or domain objects cross the boundary. */
public interface ActivityStaffing {
    record Summary(int shifts, int positions, int confirmed, int ready, int proposed, int gaps, boolean complete) {}
    Summary summarize(String sourceKind, UUID sourceId);
}
