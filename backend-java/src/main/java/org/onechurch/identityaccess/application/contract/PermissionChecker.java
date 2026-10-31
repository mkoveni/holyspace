package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
/** Published access policy for other bounded contexts. Always evaluates the trusted current caller. */
public interface PermissionChecker {
    boolean hasPermission(String permission);
    void requirePermission(String permission);
    void requirePermission(UUID claimedActorId, String permission);
}
