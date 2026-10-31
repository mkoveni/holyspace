package org.onechurch.identityaccess.application.command.changepassword;

import java.util.UUID;

public record ChangePasswordCommand(UUID actorId, UUID id, String currentPassword, String password,
    boolean administratorReset) {

    @Override
    public String toString() {
        return "ChangePasswordCommand[redacted]";
    }
}
