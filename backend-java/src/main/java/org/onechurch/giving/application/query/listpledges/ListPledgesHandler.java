package org.onechurch.giving.application.query.listpledges;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;

import java.util.List;
public final class ListPledgesHandler {
    private final GivingAccess access;
    private final PledgeReader reader;
    public ListPledgesHandler(GivingAccess access, PledgeReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public PledgeReader.Page handle(ListPledgesQuery q) {
        access.read();
        GivingRules.page(q.limit(), q.offset());
        if (q.status() != null && !List.of("OPEN", "CANCELLED").contains(q.status())) {
            throw GivingException.invalid("Choose open or cancelled pledges.");
        }
        return reader.list(q.donorId(), q.status(), q.limit(), q.offset());
    }
}
