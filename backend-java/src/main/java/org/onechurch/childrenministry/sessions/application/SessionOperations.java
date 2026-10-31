package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinks;
import org.onechurch.childrenministry.sessions.domain.CapacityPolicy;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.onechurch.volunteering.application.contract.StaffingUnavailable;

import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.List;

public final class SessionOperations {
    private final SessionReader sessions;
    private final ParticipationReader participation;
    private final AttendanceReader attendance;
    private final PersonDirectory people;
    private final AccountDirectory accounts;
    private final StaffingDirectory volunteerStaffing;
    private final VolunteerStaffLinks volunteerLinks;
    public SessionOperations(SessionReader sessions, ParticipationReader participation, AttendanceReader attendance,
        PersonDirectory people, AccountDirectory accounts, StaffingDirectory volunteerStaffing,
        VolunteerStaffLinks volunteerLinks) {
        this.sessions = sessions;
        this.participation = participation;
        this.attendance = attendance;
        this.people = people;
        this.accounts = accounts;
        this.volunteerStaffing = volunteerStaffing;
        this.volunteerLinks = volunteerLinks;
    }
    public LocalDate date(MinistrySession session) {
        Room room = sessions.room(session.roomId()).orElseThrow(() -> ChildrenException.missing("Room"));
        Location location =
            sessions.location(room.locationId()).orElseThrow(() -> ChildrenException.missing("Location"));
        return session.startsAt().atZone(ZoneId.of(location.timeZone())).toLocalDate();
    }
    public SessionView view(MinistrySession session, Instant now) {
        MinistryClass group =
            participation.ministryClass(session.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
        Programme programme =
            participation.programme(group.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        Room room = sessions.room(session.roomId()).orElseThrow(() -> ChildrenException.missing("Room"));
        Location location =
            sessions.location(room.locationId()).orElseThrow(() -> ChildrenException.missing("Location"));
        LocalDate on = session.startsAt().atZone(ZoneId.of(location.timeZone())).toLocalDate();
        List<SessionView.Staff> staff = sessions.staff(session.id()).stream().map(assignment -> {
            Worker worker =
                sessions.worker(assignment.workerId()).orElseThrow(() -> ChildrenException.missing("Worker"));
            PersonDirectory.Entry person =
                people.get(worker.personId()).orElseThrow(() -> ChildrenException.missing("Person"));
            List<String> issues = new ArrayList<>();
            volunteerLinks.find(session.id(), worker.personId()).ifPresent(link -> {
                try {
                    StaffingDirectory.Assessment assessment = volunteerStaffing.assess(link.assignmentId(),
                        session.id(), assignment.role().name(), session.startsAt(), session.endsAt());
                    issues.addAll(assessment.issues());
                } catch (StaffingUnavailable unavailable) {
                    issues.add("The linked volunteer assignment is unavailable.");
                }
            });
            if (!worker.eligible(now, session.endsAt())) {
                issues.add("Screening/training must cover this session and clearance must not be revoked.");
            }
            if (!accounts.active(worker.accountId())) {
                issues.add("Linked account is inactive.");
            }
            if (!person.available()) {
                issues.add("Person is archived or deceased.");
            }
            try {
                ChildrenRules.adult(person.birthDate(), on);
            } catch (ChildrenException invalid) {
                issues.add(invalid.getMessage());
            }
            return new SessionView.Staff(person.id(), person.name(), assignment.role(), assignment.present(),
                List.copyOf(issues));
        }).toList();
        int eligible = (int) staff.stream().filter(value -> value.present() && value.issues().isEmpty()).count();
        boolean leader = staff.stream()
            .anyMatch(value -> value.present() && value.role() == StaffRole.LEADER && value.issues().isEmpty());
        int capacity = CapacityPolicy.capacity(session.seatLimit(), room.capacity(), eligible, leader,
            programme.minStaff(), programme.childrenPerStaff());
        int count = attendance.occupancy(session.id());
        return new SessionView(session, group.name(), room.name(), location.timeZone(), capacity, count,
            capacity == 0 || count > capacity, staff);
    }
    public void requireSpace(MinistrySession session, Instant now) {
        SessionView view = view(session, now);
        if (view.staffingNeedsReview() || view.occupancy() >= view.effectiveCapacity()) {
            throw ChildrenException.conflict("Session is full or its present staffing needs review.");
        }
    }
}
