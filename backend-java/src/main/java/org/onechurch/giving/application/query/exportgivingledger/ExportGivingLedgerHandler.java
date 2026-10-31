package org.onechurch.giving.application.query.exportgivingledger;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingReports;
import org.onechurch.giving.application.contract.GivingLedgerReader;
public final class ExportGivingLedgerHandler {
    private final GivingAccess access;
    private final GivingReports reports;
    public ExportGivingLedgerHandler(GivingAccess access, GivingReports reports) {
        this.access = access;
        this.reports = reports;
    }
    public GivingLedgerReader.Report handle(ExportGivingLedgerQuery q) {
        access.export();
        return reports.report(q.filter());
    }
}
