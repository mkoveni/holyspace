package org.onechurch.volunteering.application;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;

import java.util.List;
import java.util.UUID;
public final class MemberVolunteerActions {
    private final AuthenticatedAccount identity;
    private final PermissionChecker auth;
    private final VolunteerReader volunteers;
    private final VolunteerProfiles profiles;
    private final RosterPlanning roster;
    private final UnitOfWork tx;
    private final RosterReader reader;
    public MemberVolunteerActions(AuthenticatedAccount identity, PermissionChecker auth, VolunteerReader volunteers,
        VolunteerProfiles profiles, RosterPlanning roster, UnitOfWork tx, RosterReader reader) {
        this.identity = identity;
        this.auth = auth;
        this.volunteers = volunteers;
        this.profiles = profiles;
        this.roster = roster;
        this.tx = tx;
        this.reader = reader;
    }
    private Volunteer own(UUID account) {
        auth.requirePermission(account, "volunteering.serve");
        return volunteers.byAccount(account).orElseThrow(() -> VolunteerException.missing("Linked volunteer profile"));
    }
    private void recheck(UUID account, Volunteer locked) {
        auth.requirePermission(account, "volunteering.serve");
        if (!identity.requireAccount().equals(account) || !account.equals(locked.accountId())
            || !locked.status().equals("ACTIVE")) {
            throw VolunteerException.forbidden();
        }
    }
    public void respond(UUID invitation, long version, String decision) {
        UUID account = identity.requireAccount();
        Volunteer before = own(account);
        UUID assignment =
            reader.invitation(invitation).orElseThrow(() -> VolunteerException.missing("Invitation")).assignmentId();
        if (reader.assignment(assignment).filter(a -> a.volunteerId().equals(before.id())).isEmpty()) {
            throw VolunteerException.missing("Invitation");
        }
        tx.execute(() -> {
            Volunteer locked = profiles.lock(before.id());
            recheck(account, locked);
            roster.respond(account, account, invitation, version, decision);
            return null;
        });
    }
    public void availability(UUID expectedVolunteer, long version, List<VolunteerProfiles.Weekly> weekly,
        List<VolunteerProfiles.ExceptionWindow> exceptions) {
        UUID account = identity.requireAccount();
        Volunteer before = own(account);
        if (!before.id().equals(expectedVolunteer)) {
            throw VolunteerException.conflict("Your volunteer profile changed. Refresh before editing availability.");
        }
        tx.execute(() -> {
            Volunteer locked = profiles.lock(before.id());
            recheck(account, locked);
            profiles.availability(account, locked.id(), version, weekly, exceptions);
            return null;
        });
    }
}
