package org.onechurch.payments.application.command.saveprovidersettings;
import org.onechurch.payments.domain.ProviderCredentials;

import java.util.UUID;
public record SaveProviderSettingsCommand(UUID actorId, String provider, String mode, long expectedVersion,
    boolean enabled, boolean monthly, String publicUrl, String ipRanges, ProviderCredentials replacements,
    String reason, String backendUrl) {
    @Override
    public String toString() {
        return "SaveProviderSettingsCommand[provider=" + provider + ", credentials=redacted]";
    }
}
