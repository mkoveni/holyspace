package org.onechurch.payments.application.query.listpayments;
public record ListPaymentsQuery(int limit, int offset, boolean reviewOnly) {}
