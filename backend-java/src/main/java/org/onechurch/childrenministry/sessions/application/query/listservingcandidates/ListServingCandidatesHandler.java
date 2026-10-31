package org.onechurch.childrenministry.sessions.application.query.listservingcandidates;

import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.onechurch.volunteering.application.contract.StaffingUnavailable;

import java.util.List;

public final class ListServingCandidatesHandler {
    public record Candidate(StaffingDirectory.Assessment assignment, boolean workerRegistered) {}
    private final PermissionChecker auth;
    private final SessionReader sessions;
    private final StaffingDirectory staffing;
    public ListServingCandidatesHandler(PermissionChecker auth, SessionReader sessions, StaffingDirectory staffing) {
        this.auth = auth;
        this.sessions = sessions;
        this.staffing = staffing;
    }
    public List<Candidate> handle(ListServingCandidatesQuery query) {
        auth.requirePermission(query.actorId(), "children.manage");
        MinistrySession session =
            sessions.ministrySession(query.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        try {
            return staffing.candidates(session.id(), session.startsAt(), session.endsAt())
                .stream()
                .filter(a -> List.of("LEADER", "ASSISTANT").contains(a.responsibility()))
                .map(a -> new Candidate(a, sessions.worker(a.personId()).isPresent()))
                .toList();
        } catch (StaffingUnavailable unavailable) {
            throw ChildrenException.conflict(
                "Serving assignments are unavailable or exceed the review limit. Review the serving schedule.");
        }
    }
}
