package org.onechurch.identityaccess.application.contract;
import java.util.Collection;
import java.util.Map;
import java.util.UUID;
public interface AccountNames {
    Map<UUID, String> names(Collection<UUID> ids);
}
