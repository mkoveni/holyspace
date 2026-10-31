package org.onechurch.giving.application;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.giving.application.contract.DonorIdentityGuard;
import org.onechurch.giving.application.contract.DonorLinkReader;
import org.onechurch.giving.domain.DonorLink;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.Optional;
import java.util.UUID;
public final class VerifiedDonorIdentity implements DonorIdentity, DonorIdentityGuard {
    private final AuthenticatedAccount account;
    private final DonorLinkReader links;
    private final PersonDirectory people;
    private final DonorLinkRepository repository;
    public VerifiedDonorIdentity(AuthenticatedAccount account, DonorLinkReader links, PersonDirectory people,
        DonorLinkRepository repository) {
        this.account = account;
        this.links = links;
        this.people = people;
        this.repository = repository;
    }
    @Override
    public void lockOwn(UUID actor, UUID donor) {
        if (!account.requireAccount().equals(actor)) {
            throw GivingException.forbidden();
        }
        repository.lock(actor)
            .filter(DonorLink::active)
            .filter(link -> link.personId().equals(donor))
            .orElseThrow(GivingException::forbidden);
        requireOwn(actor, donor);
    }
    @Override
    public Optional<Donor> current() {
        return links.link(account.requireAccount())
            .filter(DonorLink::active)
            .flatMap(link -> people.get(link.personId()))
            .filter(PersonDirectory.Entry::available)
            .map(person -> new Donor(person.id(), person.name()));
    }
    @Override
    public UUID requireOwn(UUID actorId, UUID donorId) {
        if (!account.requireAccount().equals(actorId)) {
            throw GivingException.forbidden();
        }
        Donor donor = current().orElseThrow(GivingException::forbidden);
        if (donorId != null && !donor.id().equals(donorId)) {
            throw GivingException.forbidden();
        }
        return donor.id();
    }
}
