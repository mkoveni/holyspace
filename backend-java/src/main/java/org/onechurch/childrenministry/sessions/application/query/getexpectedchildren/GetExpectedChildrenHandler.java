package org.onechurch.childrenministry.sessions.application.query.getexpectedchildren;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetExpectedChildrenHandler {
    private final PermissionChecker authorization;
    private final StaffScope scope;
    private final SessionReader sessions;
    private final ParticipationReader participation;
    private final SessionOperations operations;
    private final ParticipantAccess people;
    public GetExpectedChildrenHandler(PermissionChecker authorization, StaffScope scope, SessionReader sessions,
        ParticipationReader participation, SessionOperations operations, ParticipantAccess people) {
        this.authorization = authorization;
        this.scope = scope;
        this.sessions = sessions;
        this.participation = participation;
        this.operations = operations;
        this.people = people;
    }
    public List<ExpectedChild> handle(GetExpectedChildrenQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        scope.require(query.actorId(), query.sessionId());
        ChildrenRules.page(query.limit(), query.offset());
        MinistrySession session =
            sessions.ministrySession(query.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        return participation
            .expectedChildren(session.classId(), operations.date(session), query.limit(), query.offset())
            .stream()
            .map(assignment -> new ExpectedChild(assignment.childId(), people.person(assignment.childId()).name(),
                assignment.enrolmentId(), assignment.id()))
            .toList();
    }
}
