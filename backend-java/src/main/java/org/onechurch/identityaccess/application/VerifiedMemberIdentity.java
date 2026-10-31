package org.onechurch.identityaccess.application;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.identityaccess.application.contract.MemberLinkReader;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.MemberLink;
import org.onechurch.identityaccess.domain.repository.MemberLinkRepository;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.Optional;
import java.util.UUID;
public final class VerifiedMemberIdentity implements MemberIdentity, MemberIdentityGuard {
    private final Authorization auth;
    private final MemberLinkReader links;
    private final MemberLinkRepository repository;
    private final PersonDirectory people;
    public VerifiedMemberIdentity(Authorization auth, MemberLinkReader links, MemberLinkRepository repository,
        PersonDirectory people) {
        this.auth = auth;
        this.links = links;
        this.repository = repository;
        this.people = people;
    }
    public Optional<UUID> currentPerson() {
        return activePerson(auth.requireAccount());
    }
    public Optional<UUID> activePerson(UUID account) {
        return links.memberLink(account)
            .filter(MemberLink::active)
            .flatMap(link -> people.get(link.personId()))
            .filter(PersonDirectory.Entry::available)
            .map(PersonDirectory.Entry::id);
    }
    public Optional<UUID> boundPerson(UUID account) {
        return links.memberLink(account).map(MemberLink::personId);
    }
    public UUID requirePerson(UUID actor) {
        auth.requireSelf(actor);
        return currentPerson().orElseThrow(() -> new IdentityException(IdentityException.Kind.FORBIDDEN,
            "Ask the church office to verify your member account."));
    }
    public UUID lockPerson(UUID actor) {
        auth.requireSelf(actor);
        repository.lock(actor)
            .filter(MemberLink::active)
            .orElseThrow(() -> new IdentityException(IdentityException.Kind.FORBIDDEN, "Member access is not active."));
        return requirePerson(actor);
    }
}
