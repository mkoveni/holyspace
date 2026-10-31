package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
public interface AccountDirectory {
    boolean active(UUID accountId);
}
