package org.onechurch.giving.application.query.listpledgegiftcandidates;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.domain.GivingRules;

import java.util.List;
public final class ListPledgeGiftCandidatesHandler {
    private final GivingAccess access;
    private final PledgeReader reader;
    public ListPledgeGiftCandidatesHandler(GivingAccess access, PledgeReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public List<PledgeReader.Candidate> handle(ListPledgeGiftCandidatesQuery q) {
        access.read();
        GivingRules.page(q.limit(), q.offset());
        return reader.candidates(q.pledgeId(), q.limit(), q.offset());
    }
}
