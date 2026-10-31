package org.onechurch.childrenministry.sessions.application.command.importservingstaff;

import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffCommand;
import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffHandler;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinkWriter;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.onechurch.volunteering.application.contract.StaffingUnavailable;

public final class ImportServingStaffHandler {
    private final PermissionChecker auth;
    private final PersonDirectory people;
    private final SessionReader sessions;
    private final SessionRepository repository;
    private final StaffingDirectory staffing;
    private final VolunteerStaffLinkWriter links;
    private final SetSessionStaffHandler staff;
    private final UnitOfWork tx;
    public ImportServingStaffHandler(PermissionChecker auth, PersonDirectory people, SessionReader sessions,
        SessionRepository repository, StaffingDirectory staffing, VolunteerStaffLinkWriter links,
        SetSessionStaffHandler staff, UnitOfWork tx) {
        this.auth = auth;
        this.people = people;
        this.sessions = sessions;
        this.repository = repository;
        this.staffing = staffing;
        this.links = links;
        this.staff = staff;
        this.tx = tx;
    }
    public void handle(ImportServingStaffCommand command) {
        auth.requirePermission(command.actorId(), "children.manage");
        tx.execute(() -> {
            MinistrySession before =
                sessions.ministrySession(command.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
            StaffingDirectory.Assessment candidate = assessment(command, before);
            people.lock(candidate.personId());
            MinistrySession session = repository.ministrySessionForUpdate(command.sessionId()).orElseThrow();
            candidate = assessment(command, session);
            if (!candidate.ready()) {
                throw ChildrenException.conflict(String.join(" ", candidate.issues()));
            }
            sessions.worker(candidate.personId())
                .orElseThrow(() -> ChildrenException
                    .conflict("Register and verify this person as a Children Ministry worker in Spaces & team first."));
            StaffRole role;
            try {
                role = StaffRole.valueOf(command.responsibility());
            } catch (IllegalArgumentException invalid) {
                throw ChildrenException.invalid("Choose leader or assistant.");
            }
            links.save(session.id(), candidate.personId(), command.assignmentId(), role.name());
            staff.handle(new SetSessionStaffCommand(command.actorId(), session.id(), command.expectedVersion(),
                candidate.personId(), role, command.present()));
            return null;
        });
    }
    private StaffingDirectory.Assessment assessment(ImportServingStaffCommand c, MinistrySession s) {
        try {
            return staffing.assess(c.assignmentId(), s.id(), c.responsibility(), s.startsAt(), s.endsAt());
        } catch (StaffingUnavailable unavailable) {
            throw ChildrenException.conflict("The serving assignment is no longer available. Refresh the roster.");
        }
    }
}
