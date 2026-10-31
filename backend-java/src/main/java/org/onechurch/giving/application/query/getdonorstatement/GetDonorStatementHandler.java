package org.onechurch.giving.application.query.getdonorstatement;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingReports;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingException;
public final class GetDonorStatementHandler {
    private final GivingAccess access;
    private final GivingReports reports;
    public GetDonorStatementHandler(GivingAccess access, GivingReports reports) {
        this.access = access;
        this.reports = reports;
    }
    public GivingLedgerReader.Report handle(GetDonorStatementQuery q) {
        access.read();
        if (q.donorId() == null) {
            throw GivingException.invalid("Choose a donor for the statement.");
        }
        return reports.report(new GivingReader.Filter(q.from(), q.to(), null, null, q.donorId(), 25, 0));
    }
}
