package org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.childrenministry.sessions.application.SessionChildPreparation;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.LocalDate;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public final class GetFamilyArrivalReferenceHandler {
    public record FamilyArrivalReview(MemberFamilyViews.FamilyChild child, List<String> issues) {}
    private final AuthenticatedAccount account;
    private final PermissionChecker auth;
    private final StaffScope scope;
    private final SessionReader sessions;
    private final ParticipationReader participation;
    private final PersonDirectory people;
    private final SessionChildPreparation preparation;
    private final SessionOperations operations;
    private final Clock clock;
    public GetFamilyArrivalReferenceHandler(AuthenticatedAccount account, PermissionChecker auth, StaffScope scope,
        SessionReader sessions, ParticipationReader participation, PersonDirectory people,
        SessionChildPreparation preparation, SessionOperations operations, Clock clock) {
        this.account = account;
        this.auth = auth;
        this.scope = scope;
        this.sessions = sessions;
        this.participation = participation;
        this.people = people;
        this.preparation = preparation;
        this.operations = operations;
        this.clock = clock;
    }
    public FamilyArrivalReview handle(GetFamilyArrivalReferenceQuery q) {
        UUID actor = account.requireAccount();
        auth.requirePermission(actor, "children.station");
        scope.require(actor, q.sessionId());
        MinistrySession session =
            sessions.ministrySession(q.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        LocalDate date = operations.date(session);
        ClassAssignment assignment = participation.assignmentAt(session.classId(), q.childId(), date)
            .orElseThrow(() -> ChildrenException.missing("Expected child"));
        participation.enrolmentAt(assignment.programmeId(), q.childId(), date)
            .filter(e -> e.id().equals(assignment.enrolmentId()))
            .orElseThrow(() -> ChildrenException.missing("Expected child"));
        PersonDirectory.Entry child = people.get(q.childId())
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> ChildrenException.missing("Expected child"));
        return new FamilyArrivalReview(new MemberFamilyViews.FamilyChild(child.id(), child.name()),
            preparation.issues(session, Map.of(child.id(), child), clock.instant()).get(child.id()));
    }
}
