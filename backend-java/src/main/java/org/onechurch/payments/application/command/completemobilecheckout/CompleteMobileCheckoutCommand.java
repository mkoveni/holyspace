package org.onechurch.payments.application.command.completemobilecheckout;
public record CompleteMobileCheckoutCommand(String token) {
    @Override
    public String toString() {
        return "CompleteMobileCheckoutCommand[redacted]";
    }
}
