package org.onechurch.childrenministry.sessions.application.command.applyteamplan;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningWriter;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.List;
import java.util.Objects;
import java.util.UUID;
import java.util.stream.Stream;
public final class ApplyTeamPlanHandler {
    private final PermissionChecker authorization;
    private final TeamPlanning planning;
    private final SessionRepository sessions;
    private final TeamPlanningWriter writer;
    private final UnitOfWork transactions;
    private final ChildrenAudit audit;
    private final Clock clock;
    public ApplyTeamPlanHandler(PermissionChecker authorization, TeamPlanning planning, SessionRepository sessions,
        TeamPlanningWriter writer, UnitOfWork transactions, ChildrenAudit audit, Clock clock) {
        this.authorization = authorization;
        this.planning = planning;
        this.sessions = sessions;
        this.writer = writer;
        this.transactions = transactions;
        this.audit = audit;
        this.clock = clock;
    }
    public void handle(ApplyTeamPlanCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        planning.validate(command.draft());
        String reason = ChildrenRules.text(command.reason(), 500);
        transactions.execute(() -> {
            List<MinistrySession> locked = command.draft()
                .targets()
                .stream()
                .map(target -> target.sessionId())
                .sorted(Comparator.comparing(UUID::toString))
                .map(
                    id -> sessions.ministrySessionForUpdate(id).orElseThrow(() -> ChildrenException.missing("Session")))
                .toList();
            Stream.of(command.draft().workerId(), command.draft().replaceWorkerId())
                .filter(Objects::nonNull)
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .forEach(id -> sessions.workerForUpdate(id).orElseThrow(() -> ChildrenException.missing("Worker")));
            TeamPlanningView.Preview preview = planning.preview(command.draft());
            if (!preview.blockers().isEmpty()) {
                throw ChildrenException.conflict(String.join(" ", preview.blockers()));
            }
            Instant now = clock.instant();
            if (locked.stream().anyMatch(session -> !session.startsAt().isAfter(now))) {
                throw ChildrenException.conflict("A selected session has started. Reload the planning workspace.");
            }
            for (MinistrySession session : locked) {
                if (command.draft().replaceWorkerId() != null) {
                    writer.removeAssignment(session.id(), command.draft().replaceWorkerId());
                }
                sessions.insertSessionStaff(
                    new SessionStaff(session.id(), command.draft().workerId(), command.draft().role(), false));
                sessions.updateMinistrySession(session.status(session.status(), session.version()));
                audit.record(command.actorId(), "session.team.planned", session.id(), reason, now);
            }
            return null;
        });
    }
}
