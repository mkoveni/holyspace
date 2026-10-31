package org.onechurch.giving.application.command.updatefund;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class UpdateFundHandler {
    private final GivingAccess access;
    private final GivingRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;

    public UpdateFundHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
    }
    public void handle(UpdateFundCommand c) {
        access.manage(c.actorId());
        String reason = GivingRules.text(c.reason(), 1000, "Change reason");
        tx.execute(() -> {
            Fund current = repository.lockFund(c.fundId()).orElseThrow(GivingException::missing);
            access.manage(c.actorId());
            repository.saveFund(current.revise(c.expectedVersion(), c.name(), c.description(), c.active()));
            repository.audit(current.id(), "FUND", "FUND_UPDATED", reason, c.actorId(), clock.instant());
            return null;
        });
    }
}
