package org.onechurch.identityaccess.application.command.login;
import org.onechurch.identityaccess.domain.IdentityException;
public record LoginCommand(String username, String password, String deviceName) {
    public LoginCommand(String username, String password) {
        this(username, password, "Web browser");
    }
    public LoginCommand {
        deviceName = deviceName == null || deviceName.isBlank() ? "Web browser" : deviceName.trim();
        if (deviceName.length() > 80) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Device name must be at most 80 characters.");
        }
    }
    @Override
    public String toString() {
        return "LoginCommand[redacted]";
    }
}
