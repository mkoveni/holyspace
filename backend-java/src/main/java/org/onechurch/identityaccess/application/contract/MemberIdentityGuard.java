package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
public interface MemberIdentityGuard {
    UUID lockPerson(UUID actor);
}
