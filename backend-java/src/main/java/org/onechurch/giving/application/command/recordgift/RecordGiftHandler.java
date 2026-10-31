package org.onechurch.giving.application.command.recordgift;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Money;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

public final class RecordGiftHandler {
    private final GivingAccess access;
    private final GivingRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PersonDirectory people;
    public RecordGiftHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock,
        PersonDirectory people) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.people = people;
    }
    public UUID handle(RecordGiftCommand c) {
        access.manage(c.actorId());
        if (!List.of("CASH", "BANK", "OTHER").contains(c.method() == null ? "" : c.method())) {
            throw GivingException.invalid("Online gifts require verified Payments confirmation.");
        }
        Gift gift = new Gift(c.requestId(), c.donorId(), new Money(c.amountMinor(), c.currency()), c.receivedOn(),
            c.method(), c.externalReference(), c.allocations(), c.actorId(), clock.instant());
        GivingRules.date(gift.receivedOn(), LocalDate.now(clock));
        String hash = GivingHash.of("gift", c.actorId(), gift.donorId(), gift.money(), gift.receivedOn(), gift.method(),
            gift.externalReference(),
            gift.allocations().stream().sorted(java.util.Comparator.comparing(a -> a.fundId().toString())).toList());
        return tx.execute(() -> {
            Optional<UUID> replay = repository.giftRequest(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            if (gift.donorId() != null) {
                people.lock(gift.donorId())
                    .filter(PersonDirectory.Entry::available)
                    .orElseThrow(
                        () -> GivingException.invalid("Choose an available person or record an anonymous gift."));
            }
            List<UUID> funds = gift.allocations()
                .stream()
                .map(Gift.Allocation::fundId)
                .sorted(java.util.Comparator.comparing(UUID::toString))
                .toList();
            for (UUID id : funds) {
                repository.lockFund(id)
                    .filter(Fund::active)
                    .orElseThrow(() -> GivingException.conflict("An allocated fund is missing or inactive."));
            }
            access.manage(c.actorId());
            Optional<UUID> concurrentReplay = repository.giftRequest(c.requestId(), hash);
            if (concurrentReplay.isPresent()) {
                return concurrentReplay.orElseThrow();
            }
            repository.insertGift(gift, hash);
            repository.audit(gift.id(), "GIFT", "GIFT_POSTED", "", c.actorId(), clock.instant());
            return gift.id();
        });
    }
}
