package org.onechurch.giving.application.query.listgivingfunds;
public record ListGivingFundsQuery(boolean archived, String search, int limit, int offset) {}
