package org.onechurch.childrenministry.participation.application.command.createroom;

import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateRoomHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SessionReader session;
    private final SessionRepository sessionRepository;
    public CreateRoomHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions, Clock clock,
        SessionReader session, SessionRepository sessionRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.session = session;
        this.sessionRepository = sessionRepository;
    }
    public UUID handle(CreateRoomCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            session.location(command.locationId()).orElseThrow(() -> ChildrenException.missing("Location"));
            Instant now = clock.instant();
            Room room = new Room(UUID.randomUUID(), command.locationId(), command.name(), command.capacity());
            sessionRepository.insertRoom(room);
            audit.record(command.actorId(), "room.created", room.id(), "", now);
            return room.id();
        });
    }
}
