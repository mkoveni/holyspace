package org.onechurch.childrenministry.sessions.application.command.createsession;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateSessionHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationReader participation;
    private final SessionReader session;
    private final SessionRepository sessionRepository;
    public CreateSessionHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationReader participation, SessionReader session, SessionRepository sessionRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participation = participation;
        this.session = session;
        this.sessionRepository = sessionRepository;
    }
    public UUID handle(CreateSessionCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            participation.ministryClass(command.classId())
                .orElseThrow(() -> ChildrenException.missing("MinistryClass"));
            Room room = session.room(command.roomId()).orElseThrow(() -> ChildrenException.missing("Room"));
            if (command.seatLimit() > room.capacity()) {
                throw ChildrenException.invalid("Session seats cannot exceed room capacity.");
            }
            Instant now = clock.instant();
            MinistrySession session = new MinistrySession(UUID.randomUUID(), command.classId(), room.id(),
                command.startsAt(), command.endsAt(), command.seatLimit(), SessionStatus.PLANNED, 0);
            sessionRepository.insertMinistrySession(session);
            audit.record(command.actorId(), "session.created", session.id(), "", now);
            return session.id();
        });
    }
}
