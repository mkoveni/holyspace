package org.onechurch.childrenministry.participation.application.command.createlocation;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.DateTimeException;
import java.time.Instant;
import java.util.UUID;

public final class CreateLocationHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SessionRepository sessionRepository;
    public CreateLocationHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SessionRepository sessionRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.sessionRepository = sessionRepository;
    }
    public UUID handle(CreateLocationCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            Instant now = clock.instant();
            Location location;
            try {
                location = new Location(UUID.randomUUID(), command.name(), command.timeZone());
            } catch (DateTimeException error) {
                throw ChildrenException.invalid("Select a valid IANA time zone.");
            }
            sessionRepository.insertLocation(location);
            audit.record(command.actorId(), "location.created", location.id(), "", now);
            return location.id();
        });
    }
}
