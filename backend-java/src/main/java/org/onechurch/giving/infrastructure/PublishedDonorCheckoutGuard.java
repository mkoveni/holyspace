package org.onechurch.giving.infrastructure;
import org.onechurch.giving.application.contract.DonorCheckoutGuard;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.springframework.stereotype.Component;

import java.util.UUID;
@Component
public final class PublishedDonorCheckoutGuard implements DonorCheckoutGuard {
    private final DonorLinkRepository links;
    private final PersonDirectory people;
    public PublishedDonorCheckoutGuard(DonorLinkRepository links, PersonDirectory people) {
        this.links = links;
        this.people = people;
    }
    public boolean lockEligible(UUID account, UUID donor) {
        return links.lock(account).filter(link -> link.active() && link.personId().equals(donor)).isPresent()
            && people.get(donor).filter(PersonDirectory.Entry::available).isPresent();
    }
}
