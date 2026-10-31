package org.onechurch.payments.domain;
public final class PaymentException extends RuntimeException {
    public enum Kind {
        INVALID, MISSING, CONFLICT, UNAVAILABLE
    }
    private final Kind kind;
    public PaymentException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static PaymentException invalid(String message) {
        return new PaymentException(Kind.INVALID, message);
    }
    public static PaymentException conflict(String message) {
        return new PaymentException(Kind.CONFLICT, message);
    }
    public static PaymentException unavailable(String message) {
        return new PaymentException(Kind.UNAVAILABLE, message);
    }
    public static PaymentException missing() {
        return new PaymentException(Kind.MISSING, "Payment was not found.");
    }
}
