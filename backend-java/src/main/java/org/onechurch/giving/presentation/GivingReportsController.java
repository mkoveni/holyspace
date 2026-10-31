package org.onechurch.giving.presentation;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.query.exportgivingledger.ExportGivingLedgerHandler;
import org.onechurch.giving.application.query.exportgivingledger.ExportGivingLedgerQuery;
import org.onechurch.giving.application.query.getdonorstatement.GetDonorStatementHandler;
import org.onechurch.giving.application.query.getdonorstatement.GetDonorStatementQuery;
import org.onechurch.giving.presentation.api.GivingReportsApi;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;
import java.util.UUID;
@RestController
public class GivingReportsController implements GivingReportsApi {
    private final GetDonorStatementHandler getDonorStatement;
    private final ExportGivingLedgerHandler exportGivingLedger;
    public GivingReportsController(GetDonorStatementHandler getDonorStatement,
        ExportGivingLedgerHandler exportGivingLedger) {
        this.getDonorStatement = getDonorStatement;
        this.exportGivingLedger = exportGivingLedger;
    }
    private ResponseEntity<byte[]> csv(GivingLedgerReader.Report report) {
        return ResponseEntity.ok()
            .header(org.springframework.http.HttpHeaders.CONTENT_DISPOSITION,
                "attachment; filename=\"giving-ledger-" + report.from() + "-" + report.to() + ".csv\"")
            .header(org.springframework.http.HttpHeaders.CACHE_CONTROL, "no-store")
            .contentType(org.springframework.http.MediaType.parseMediaType("text/csv;charset=UTF-8"))
            .body(GivingCsv.render(report).getBytes(java.nio.charset.StandardCharsets.UTF_8));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public GivingLedgerReader.Report getDonorStatement(UUID donorId, LocalDate from, LocalDate to) {
        return getDonorStatement.handle(new GetDonorStatementQuery(donorId, from, to));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.export') and hasAnyAuthority('giving.read','giving.manage')")
    public ResponseEntity<byte[]> exportGivingLedger(LocalDate from, LocalDate to, String currency, UUID fundId,
        UUID donorId) {
        return csv(exportGivingLedger
            .handle(new ExportGivingLedgerQuery(new GivingReader.Filter(from, to, currency, fundId, donorId, 25, 0))));
    }
}
