package org.onechurch.giving.application.command.verifydonorlink;
import org.onechurch.giving.domain.DonorLink;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class VerifyDonorLinkHandler {
    private AccountIdentityGuard identityGuard;
    private MemberIdentity memberLinks;
    private final PermissionChecker auth;
    private final AccountDirectory accounts;
    private final PersonDirectory people;
    private final DonorLinkRepository links;
    private final UnitOfWork tx;
    private final Clock clock;
    public VerifyDonorLinkHandler(PermissionChecker auth, AccountDirectory accounts, PersonDirectory people,
        DonorLinkRepository links, UnitOfWork tx, Clock clock) {
        this.auth = auth;
        this.accounts = accounts;
        this.people = people;
        this.links = links;
        this.tx = tx;
        this.clock = clock;
    }
    public VerifyDonorLinkHandler(PermissionChecker auth, AccountDirectory accounts, PersonDirectory people,
        DonorLinkRepository links, UnitOfWork tx, Clock clock, AccountIdentityGuard identityGuard,
        MemberIdentity memberLinks) {
        this(auth, accounts, people, links, tx, clock);
        this.identityGuard = identityGuard;
        this.memberLinks = memberLinks;
    }
    private void authorize(VerifyDonorLinkCommand c) {
        auth.requirePermission(c.actorId(), "identity.manage");
        auth.requirePermission(c.actorId(), "giving.manage");
    }
    public void handle(VerifyDonorLinkCommand c) {
        authorize(c);
        String evidence = GivingRules.text(c.evidence(), 1000, "Identity verification evidence or revocation reason");
        tx.execute(() -> {
            if (identityGuard != null) {
                identityGuard.lock(c.accountId());
            }
            if (memberLinks != null
                && memberLinks.boundPerson(c.accountId()).filter(person -> !person.equals(c.personId())).isPresent()) {
                throw GivingException.conflict("The donor must match the verified member identity.");
            }
            DonorLink prior = links.lock(c.accountId()).orElse(null);
            authorize(c);
            if (c.active() && (!accounts.active(c.accountId())
                || people.lock(c.personId()).filter(PersonDirectory.Entry::available).isEmpty())) {
                throw GivingException.invalid("Choose an active account and available person.");
            }
            if (prior == null && (c.expectedVersion() != 0 || !c.active())) {
                throw GivingException.conflict("Refresh donor access before trying again.");
            }
            DonorLink revised =
                prior == null ? new DonorLink(c.accountId(), c.personId(), true, 1, c.actorId(), clock.instant())
                    : prior.revise(c.personId(), c.active(), c.expectedVersion(), c.actorId(), clock.instant());
            links.save(revised, evidence);
            return null;
        });
    }
}
