package org.onechurch.giving.application.query.listgifts;
import org.onechurch.giving.application.contract.GivingReader;
public record ListGiftsQuery(GivingReader.Filter filter) {}
