package org.onechurch.identityaccess.application.contract;

import java.time.Instant;

public interface LoginAttemptWriter {
    void lockLogin(String identifierHash);

    boolean throttled(String identifierHash, Instant now);

    void failed(String identifierHash, Instant now);

    void clearFailures(String identifierHash);
}
