package org.onechurch.identityaccess.application.query.authenticatesession;

public record AuthenticateSessionQuery(String token) {

    @Override
    public String toString() {
        return "AuthenticateSessionQuery[redacted]";
    }
}
