package org.onechurch.giving.application.command.issueacknowledgement;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.Acknowledgement;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.repository.AcknowledgementRepository;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public final class IssueAcknowledgementHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final AcknowledgementRepository acknowledgements;
    private final GivingReader reader;
    public IssueAcknowledgementHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        AcknowledgementRepository acknowledgements, GivingReader reader) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.acknowledgements = acknowledgements;
        this.reader = reader;
    }
    public UUID handle(IssueAcknowledgementCommand c) {
        access.acknowledge(c.actorId());
        String hash = GivingHash.of("acknowledgement", c.actorId(), c.giftId());
        return tx.execute(() -> {
            Gift gift = giving.lockGift(c.giftId()).orElseThrow(GivingException::missing);
            access.acknowledge(c.actorId());
            Optional<UUID> replay = acknowledgements.request(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            if (giving.reversed(gift.id()) || giving.refunded(gift.id()) > 0) {
                throw GivingException.conflict("An acknowledgement cannot be issued for a reversed gift.");
            }
            if (acknowledgements.forGift(gift.id()).isPresent()) {
                throw GivingException.conflict("This gift already has an acknowledgement. Refresh to open it.");
            }
            GivingReader.Detail detail = reader.detail(gift.id()).orElseThrow(GivingException::missing);
            List<Acknowledgement.Line> lines = gift.allocations()
                .stream()
                .map(a -> new Acknowledgement.Line(a.fundId(), detail.fundNames().get(a.fundId()), a.amountMinor()))
                .toList();
            Acknowledgement acknowledgement = new Acknowledgement(c.requestId(), gift.id(), detail.donorName(),
                LocalDate.now(clock), c.actorId(), clock.instant(), lines);
            acknowledgements.insert(acknowledgement, hash);
            giving.audit(gift.id(), "GIFT", "ACKNOWLEDGEMENT_ISSUED", "", c.actorId(), clock.instant());
            return acknowledgement.id();
        });
    }
}
