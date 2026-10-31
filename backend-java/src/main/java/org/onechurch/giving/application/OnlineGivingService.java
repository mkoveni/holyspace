package org.onechurch.giving.application;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Money;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.OnlineGivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.Comparator;
import java.util.List;
import java.util.UUID;
/** Trusted capability invoked only after durable provider verification; no user payment assertions. */
public final class OnlineGivingService implements OnlineGivingLedger {
    private final GivingRepository giving;
    private final OnlineGivingRepository online;
    private final PledgeRepository pledges;
    private final PersonDirectory people;
    private final UnitOfWork tx;
    private final Clock clock;
    public OnlineGivingService(GivingRepository giving, OnlineGivingRepository online, PledgeRepository pledges,
        PersonDirectory people, UnitOfWork tx, Clock clock) {
        this.giving = giving;
        this.online = online;
        this.pledges = pledges;
        this.people = people;
        this.tx = tx;
        this.clock = clock;
    }
    @Override
    public Donor validate(UUID donor, UUID fund) {
        return tx.execute(() -> {
            PersonDirectory.Entry person = people.lock(donor)
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> GivingException.invalid("Choose an available donor."));
            giving.lockFund(fund)
                .filter(org.onechurch.giving.domain.Fund::active)
                .orElseThrow(() -> GivingException.conflict("Choose an active giving fund."));
            return new Donor(person.name());
        });
    }
    @Override
    public UUID post(UUID charge, UUID donor, UUID fund, long amount, String reference, LocalDate date, UUID actor) {
        GivingRules.date(date, LocalDate.now(clock));
        String hash = GivingHash.of("verified-payment", charge, donor, fund, amount, reference, date, actor);
        return tx.execute(() -> {
            if (giving.giftRequest(charge, hash).isPresent()) {
                return charge;
            }
            people.lock(donor).orElseThrow(GivingException::missing);
            giving.lockFund(fund).orElseThrow(GivingException::missing);
            if (giving.giftRequest(charge, hash).isPresent()) {
                return charge;
            }
            Gift gift = new Gift(charge, donor, new Money(amount, "ZAR"), date, "ONLINE", reference,
                List.of(new Gift.Allocation(fund, amount)), actor, clock.instant());
            giving.insertGift(gift, hash);
            online.source(charge, charge, reference);
            giving.audit(charge, "GIFT", "PAYMENT_POSTED", reference, actor, clock.instant());
            return charge;
        });
    }
    @Override
    public void refund(UUID id, UUID giftId, long amount, String reference, LocalDate date, UUID actor) {
        tx.execute(() -> {
            Gift gift = giving.lockGift(giftId).orElseThrow(GivingException::missing);
            if (online.refundExists(id, giftId, amount, reference, date)) {
                return null;
            }
            GivingRules.date(date, LocalDate.now(clock));
            if (!gift.method().equals("ONLINE") || gift.allocations().size() != 1 || date.isBefore(gift.receivedOn())
                || amount < 1 || amount > gift.money().amountMinor() - giving.refunded(giftId)) {
                throw GivingException.conflict("Refund must match the unrefunded online gift and its dates.");
            }
            List<PledgeAssignment> assignments = online.activeAssignments(giftId);
            List<UUID> ids = assignments.stream()
                .map(PledgeAssignment::pledgeId)
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .toList();
            for (UUID pledgeId : ids) {
                Pledge pledge = pledges.lock(pledgeId).orElseThrow(GivingException::missing);
                for (PledgeAssignment assignment : assignments.stream()
                    .filter(a -> a.pledgeId().equals(pledgeId))
                    .toList()) {
                    pledges.save(assignment.cancel(assignment.version(),
                        "Source gift refunded; review the remaining credit.", actor, clock.instant()));
                }
                pledges.save(pledge.revised());
                giving.audit(pledgeId, "PLEDGE", "PAYMENT_REFUND", reference, actor, clock.instant());
            }
            online.refund(id, giftId, gift.allocations().getFirst().fundId(), amount, reference, date, actor,
                clock.instant());
            giving.audit(giftId, "GIFT", "PAYMENT_REFUNDED", reference, actor, clock.instant());
            return null;
        });
    }
}
