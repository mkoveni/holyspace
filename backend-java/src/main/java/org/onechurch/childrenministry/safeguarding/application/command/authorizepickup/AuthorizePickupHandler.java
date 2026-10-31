package org.onechurch.childrenministry.safeguarding.application.command.authorizepickup;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.UUID;

public final class AuthorizePickupHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final ParticipationReader participation;
    private final SafeguardingReader safeguarding;
    private final SafeguardingRepository safeguardingRepository;
    private final ZoneId zone;
    public AuthorizePickupHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, ParticipationReader participation, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.participation = participation;
        this.safeguarding = safeguarding;
        this.safeguardingRepository = safeguardingRepository;
        this.zone = zone;
    }
    public UUID handle(AuthorizePickupCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        return transactions.execute(() -> {
            participants.lockChild(command.childId(), command.collectorId());
            Instant now = clock.instant();
            LocalDate today = LocalDate.now(clock.withZone(zone));
            ChildrenRules.child(participants.available(command.childId()).birthDate(), today);
            ChildrenRules.adult(participants.available(command.collectorId()).birthDate(), today);
            participation.programme(command.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
            GuardianAuthority authority = safeguarding.guardianAuthority(command.authorityId())
                .orElseThrow(() -> ChildrenException.missing("GuardianAuthority"));
            if (!authority.childId().equals(command.childId())
                || !authority.covers(command.validFrom(), command.validUntil())) {
                throw ChildrenException
                    .invalid("Pickup permission requires verified guardian authority covering its period.");
            }
            PickupAuthorization grant = new PickupAuthorization(UUID.randomUUID(), command.programmeId(),
                command.childId(), command.collectorId(), authority.id(), command.validFrom(), command.validUntil(),
                command.evidenceReference(), command.actorId(), null, null, 0);
            safeguardingRepository.insertPickupAuthorization(grant);
            audit.record(command.actorId(), "pickup.authorized", grant.id(), "", now);
            return grant.id();
        });
    }
}
