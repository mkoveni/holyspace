package org.onechurch.giving.presentation;
import org.onechurch.giving.application.contract.GivingLedgerReader;
public final class GivingCsv {
    private GivingCsv() {
    }
    private static String text(String value) {
        String safe = value == null ? "" : value;
        String leading = safe.stripLeading();
        if (!leading.isEmpty() && "=+-@".indexOf(leading.charAt(0)) >= 0) {
            safe = "'" + safe;
        }
        return "\"" + safe.replace("\"", "\"\"") + "\"";
    }
    public static String render(GivingLedgerReader.Report report) {
        StringBuilder csv =
            new StringBuilder("\uFEFFdate,kind,gift_id,donor,fund,currency,amount_minor,method,reference,reason\r\n");
        for (GivingLedgerReader.Line line : report.lines()) {
            csv.append(text(line.date().toString()))
                .append(',')
                .append(text(line.kind()))
                .append(',')
                .append(text(line.giftId().toString()))
                .append(',')
                .append(text(line.donorName()))
                .append(',')
                .append(text(line.fundName()))
                .append(',')
                .append(text(line.currency()))
                .append(',')
                .append(line.amountMinor())
                .append(',')
                .append(text(line.method()))
                .append(',')
                .append(text(line.reference()))
                .append(',')
                .append(text(line.reason()))
                .append("\r\n");
        }
        return csv.toString();
    }
}
