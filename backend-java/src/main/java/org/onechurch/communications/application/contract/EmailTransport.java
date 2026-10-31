package org.onechurch.communications.application.contract;
public interface EmailTransport {
    record Configuration(boolean enabled, boolean capture, String mode) {}
    record Result(String status, String errorCode, boolean captured) {}
    Configuration configuration();
    Result send(DeliverySnapshot delivery);
}
