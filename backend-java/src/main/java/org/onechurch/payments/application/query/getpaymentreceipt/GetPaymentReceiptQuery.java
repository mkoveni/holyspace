package org.onechurch.payments.application.query.getpaymentreceipt;
import java.util.UUID;
public record GetPaymentReceiptQuery(UUID orderId, UUID chargeId, UUID contactId) {}
