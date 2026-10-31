package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.time.LocalDate;
import java.util.UUID;
@Tag(name = "Giving · GivingReports")
public interface GivingReportsApi {

    @GetMapping(value = "/api/giving/statements/{donorId}")

    @Operation(summary = "getDonorStatement", description = "Printable current-record statement. Inclusive dates, separate currency totals and reversal dates; a reversal can refer to an earlier gift. Maximum 5000 allocation lines; narrow oversized periods. This is not a tax certificate.")
    GivingLedgerReader.Report getDonorStatement(@PathVariable UUID donorId, @RequestParam LocalDate from,
        @RequestParam LocalDate to);
    @GetMapping(value = "/api/giving/ledger.csv", produces = "text/csv")

    @Operation(summary = "exportGivingLedger", description = "Requires giving.read and giving.export. Complete filtered period, maximum 5000 allocation lines; never truncates silently. Signed integer minor units, escaped text and separate currencies.")
    ResponseEntity<byte[]> exportGivingLedger(@RequestParam LocalDate from, @RequestParam LocalDate to,
        @RequestParam(required = false) String currency, @RequestParam(required = false) UUID fundId,
        @RequestParam(required = false) UUID donorId);
}
