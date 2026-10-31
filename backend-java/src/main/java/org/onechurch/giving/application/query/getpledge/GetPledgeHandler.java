package org.onechurch.giving.application.query.getpledge;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
public final class GetPledgeHandler {
    private final GivingAccess access;
    private final PledgeReader reader;
    public GetPledgeHandler(GivingAccess access, PledgeReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public PledgeReader.Detail handle(GetPledgeQuery q) {
        access.read();
        GivingRules.page(q.limit(), q.offset());
        return reader.detail(q.pledgeId(), q.limit(), q.offset()).orElseThrow(GivingException::missing);
    }
}
