package org.onechurch.identityaccess.application.contract;
import java.util.Optional;
import java.util.UUID;
/** General member identity. Financial, ministry and guardian capabilities remain independently authorized. */
public interface MemberIdentity {
    Optional<UUID> currentPerson();
    Optional<UUID> activePerson(UUID account);
    Optional<UUID> boundPerson(UUID account);
    UUID requirePerson(UUID actor);
}
