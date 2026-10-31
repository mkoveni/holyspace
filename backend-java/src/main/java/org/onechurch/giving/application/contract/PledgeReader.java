package org.onechurch.giving.application.contract;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PledgeReader {
    record Summary(Pledge pledge, String donorName, String fundName, long creditedMinor, long remainingMinor,
        boolean fulfilled) {}
    record Page(List<Summary> items, long total) {}
    record Assignment(PledgeAssignment assignment, LocalDate receivedOn, boolean giftReversed) {}
    record Detail(Summary summary, List<Assignment> assignments, List<GivingReader.History> history) {}
    record Candidate(UUID giftId, LocalDate receivedOn, long availableMinor, String reference, String method) {}
    Page list(UUID donorId, String status, int limit, int offset);
    Optional<Detail> detail(UUID id, int limit, int offset);
    List<Candidate> candidates(UUID id, int limit, int offset);
}
