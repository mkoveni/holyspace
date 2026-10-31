package org.onechurch.payments.application.contract;
public interface YocoWebhookWriter {
    record Registered(String id, String secret) {
        @Override
        public String toString() {
            return "Registered[id=" + id + ", secret=redacted]";
        }
    }
    Registered register(String secretKey, String name, String callback, String mode);
    void remove(String secretKey, String id);
}
