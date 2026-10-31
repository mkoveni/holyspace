package org.onechurch.giving.application.query.listgivingfunds;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingRules;
public final class ListGivingFundsHandler {
    private final GivingAccess access;
    private final GivingReader reader;
    public ListGivingFundsHandler(GivingAccess access, GivingReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public GivingReader.FundPage handle(ListGivingFundsQuery q) {
        access.read();
        GivingRules.page(q.limit(), q.offset());
        return reader.funds(q.archived(), q.search(), q.limit(), q.offset());
    }
}
