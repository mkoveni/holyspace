package org.onechurch.giving.infrastructure;
import org.onechurch.giving.application.contract.DonorLinkReader;
import org.onechurch.identityaccess.application.contract.PersonBindingPolicy;
import org.springframework.stereotype.Component;

import java.util.UUID;
@Component
public final class DonorPersonBindingPolicy implements PersonBindingPolicy {
    private final DonorLinkReader links;
    public DonorPersonBindingPolicy(DonorLinkReader links) {
        this.links = links;
    }
    public boolean permits(UUID account, UUID person) {
        return links.link(account).map(link -> link.personId().equals(person)).orElse(true);
    }
}
