package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.springframework.stereotype.Component;

import java.util.Collection;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
@Component
public class PublishedAccountNames implements AccountNames {
    private final AccountReader reader;
    public PublishedAccountNames(AccountReader reader) {
        this.reader = reader;
    }
    public Map<UUID, String> names(Collection<UUID> ids) {
        if (ids.size() > 100) {
            throw new IllegalArgumentException("Account names must be requested in pages of at most 100.");
        }
        Map<UUID, String> names = new HashMap<>();
        ids.stream()
            .distinct()
            .forEach(id -> reader.find(id).ifPresent(account -> names.put(id, account.displayName())));
        return Map.copyOf(names);
    }
}
