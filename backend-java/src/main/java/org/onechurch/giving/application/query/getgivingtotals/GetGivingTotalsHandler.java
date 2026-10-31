package org.onechurch.giving.application.query.getgivingtotals;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingFilters;
import org.onechurch.giving.application.contract.GivingReader;

import java.util.List;
public final class GetGivingTotalsHandler {
    private final GivingAccess access;
    private final GivingReader reader;
    public GetGivingTotalsHandler(GivingAccess access, GivingReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public List<GivingReader.Total> handle(GetGivingTotalsQuery q) {
        access.read();
        GivingFilters.validate(q.filter());
        return reader.totals(q.filter());
    }
}
