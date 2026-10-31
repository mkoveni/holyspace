package org.onechurch.giving.application.query.getgivingtotals;
import org.onechurch.giving.application.contract.GivingReader;
public record GetGivingTotalsQuery(GivingReader.Filter filter) {}
