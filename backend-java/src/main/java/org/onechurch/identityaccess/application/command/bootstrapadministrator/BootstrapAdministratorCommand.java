package org.onechurch.identityaccess.application.command.bootstrapadministrator;

public record BootstrapAdministratorCommand(String username, String email, String displayName, String password) {

    @Override
    public String toString() {
        return "BootstrapAdministratorCommand[redacted]";
    }
}
