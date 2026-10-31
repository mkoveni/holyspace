package org.onechurch.giving.application.command.setonlinefund;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class SetOnlineFundHandler {
    private final PermissionChecker auth;
    private final GivingRepository funds;
    private final DonorLinkRepository links;
    private final UnitOfWork tx;
    private final Clock clock;
    public SetOnlineFundHandler(PermissionChecker auth, GivingRepository funds, DonorLinkRepository links,
        UnitOfWork tx, Clock clock) {
        this.auth = auth;
        this.funds = funds;
        this.links = links;
        this.tx = tx;
        this.clock = clock;
    }
    public void handle(SetOnlineFundCommand c) {
        auth.requirePermission(c.actorId(), "giving.manage");
        tx.execute(() -> {
            Fund fund = funds.lockFund(c.fundId()).orElseThrow(GivingException::missing);
            auth.requirePermission(c.actorId(), "giving.manage");
            if (c.enabled() && !fund.active()) {
                throw GivingException.invalid("Activate this fund before offering it online.");
            }
            funds.saveFund(fund.revise(c.expectedVersion(), fund.name(), fund.description(), fund.active()));
            links.online(fund.id(), c.enabled());
            funds.audit(fund.id(), "FUND", "ONLINE_GIVING_CHANGED",
                c.enabled() ? "Offered to donors online." : "Removed from online giving.", c.actorId(),
                clock.instant());
            return null;
        });
    }
}
