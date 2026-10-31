package org.onechurch.giving.application.query.exportgivingledger;
import org.onechurch.giving.application.contract.GivingReader;
public record ExportGivingLedgerQuery(GivingReader.Filter filter) {}
