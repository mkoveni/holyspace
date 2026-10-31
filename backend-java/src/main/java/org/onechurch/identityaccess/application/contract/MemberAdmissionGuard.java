package org.onechurch.identityaccess.application.contract;
import java.util.Optional;
import java.util.UUID;
/**
 * Trusted admission capability. Callers must authorize their own entry point before use. Locks member identity before
 * People and occurrence locks; never grants guardian authority.
 */
public interface MemberAdmissionGuard {
    record Identity(UUID personId, long version) {}
    Optional<Identity> lock(UUID account);
}
