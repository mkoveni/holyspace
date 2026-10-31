package org.onechurch.giving.application.query.listreconciliations;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.ReconciliationReader;
import org.onechurch.giving.domain.GivingRules;
public final class ListReconciliationsHandler {
    private final GivingAccess access;
    private final ReconciliationReader reader;
    public ListReconciliationsHandler(GivingAccess access, ReconciliationReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public ReconciliationReader.Page handle(ListReconciliationsQuery q) {
        access.read();
        GivingRules.page(q.limit(), q.offset());
        return reader.list(q.limit(), q.offset());
    }
}
