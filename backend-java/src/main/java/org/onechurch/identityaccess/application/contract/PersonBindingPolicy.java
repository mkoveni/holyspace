package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
public interface PersonBindingPolicy {
    boolean permits(UUID account, UUID person);
}
