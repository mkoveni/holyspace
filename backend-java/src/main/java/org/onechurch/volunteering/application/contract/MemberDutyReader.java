package org.onechurch.volunteering.application.contract;
import org.onechurch.volunteering.domain.Assignment;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface MemberDutyReader {
    List<Assignment> duties(UUID volunteer, Instant from, Instant until, int limit, int offset);
}
