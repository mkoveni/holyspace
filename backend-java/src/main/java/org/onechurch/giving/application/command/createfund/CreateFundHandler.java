package org.onechurch.giving.application.command.createfund;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;

public final class CreateFundHandler {
    private final GivingAccess access;
    private final GivingRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;

    public CreateFundHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID handle(CreateFundCommand c) {
        access.manage(c.actorId());
        Fund fund = new Fund(c.requestId(), c.name(), c.description(), true, 0);
        String hash = GivingHash.of("fund", c.actorId(), fund.name(), fund.description());
        return tx.execute(() -> {
            access.manage(c.actorId());
            return repository.fundRequest(c.requestId(), hash).orElseGet(() -> {
                boolean inserted = repository.insertFund(fund, hash);
                if (inserted) {
                    repository.audit(fund.id(), "FUND", "FUND_CREATED", "", c.actorId(), clock.instant());
                } else {
                    repository.fundRequest(c.requestId(), hash)
                        .orElseThrow(() -> GivingException.conflict("A fund already uses this name."));
                }
                return fund.id();
            });
        });
    }
}
