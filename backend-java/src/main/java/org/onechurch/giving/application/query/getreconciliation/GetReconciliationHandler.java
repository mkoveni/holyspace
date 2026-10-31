package org.onechurch.giving.application.query.getreconciliation;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.ReconciliationReader;
import org.onechurch.giving.domain.GivingException;
public final class GetReconciliationHandler {
    private final GivingAccess access;
    private final ReconciliationReader reader;
    public GetReconciliationHandler(GivingAccess access, ReconciliationReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public ReconciliationReader.Detail handle(GetReconciliationQuery q) {
        access.read();
        return reader.detail(q.batchId()).orElseThrow(GivingException::missing);
    }
}
