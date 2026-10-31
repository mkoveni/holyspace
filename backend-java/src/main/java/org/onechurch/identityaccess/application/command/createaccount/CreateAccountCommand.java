package org.onechurch.identityaccess.application.command.createaccount;

import java.util.UUID;

public record CreateAccountCommand(UUID actorId, String username, String email, String displayName, String password) {

    @Override
    public String toString() {
        return "CreateAccountCommand[redacted]";
    }
}
