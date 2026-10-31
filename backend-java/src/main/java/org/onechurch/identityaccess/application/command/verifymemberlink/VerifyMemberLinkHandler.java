package org.onechurch.identityaccess.application.command.verifymemberlink;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.application.contract.PersonBindingPolicy;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.MemberLink;
import org.onechurch.identityaccess.domain.repository.MemberLinkRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.List;
public final class VerifyMemberLinkHandler {
    private final Authorization auth;
    private final AccountIdentityGuard guard;
    private final AccountDirectory accounts;
    private final MemberLinkRepository links;
    private final PersonDirectory people;
    private final List<PersonBindingPolicy> policies;
    private final UnitOfWork tx;
    private final Clock clock;
    public VerifyMemberLinkHandler(Authorization auth, AccountIdentityGuard guard, AccountDirectory accounts,
        MemberLinkRepository links, PersonDirectory people, List<PersonBindingPolicy> policies, UnitOfWork tx,
        Clock clock) {
        this.auth = auth;
        this.guard = guard;
        this.accounts = accounts;
        this.links = links;
        this.people = people;
        this.policies = List.copyOf(policies);
        this.tx = tx;
        this.clock = clock;
    }
    private void authorize(VerifyMemberLinkCommand c) {
        auth.requirePermission(c.actorId(), "identity.manage");
        auth.requirePermission(c.actorId(), "people.manage");
    }
    public void handle(VerifyMemberLinkCommand c) {
        authorize(c);
        if (c.evidence() == null || c.evidence().isBlank() || c.evidence().trim().length() > 1000) {
            throw new IdentityException(IdentityException.Kind.INVALID,
                "Record verification evidence or a revocation reason (at most 1000 characters).");
        }
        tx.execute(() -> {
            guard.lock(c.accountId());
            MemberLink prior = links.lock(c.accountId()).orElse(null);
            authorize(c);
            if (c.active() && (!accounts.active(c.accountId())
                || people.lock(c.personId()).filter(PersonDirectory.Entry::available).isEmpty()
                || policies.stream().anyMatch(policy -> !policy.permits(c.accountId(), c.personId())))) {
                throw new IdentityException(IdentityException.Kind.CONFLICT,
                    "Choose an active account and available person matching existing verified financial identity.");
            }
            if (prior == null && (c.expectedVersion() != 0 || !c.active())) {
                throw new IdentityException(IdentityException.Kind.CONFLICT,
                    "Refresh member access before trying again.");
            }
            MemberLink link =
                prior == null ? new MemberLink(c.accountId(), c.personId(), true, 1, c.actorId(), clock.instant())
                    : prior.revise(c.personId(), c.active(), c.expectedVersion(), c.actorId(), clock.instant());
            links.save(link, c.evidence().trim());
            return null;
        });
    }
}
