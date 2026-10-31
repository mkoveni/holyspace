package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionSeriesReader;
import org.onechurch.childrenministry.sessions.domain.CapacityPolicy;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class SessionSeriesPlanning {
    private final SessionReader sessions;
    private final SessionSeriesReader series;
    private final ParticipationReader participation;
    private final PersonDirectory people;
    private final AccountDirectory accounts;
    private final Clock clock;
    public SessionSeriesPlanning(SessionReader sessions, SessionSeriesReader series, ParticipationReader participation,
        PersonDirectory people, AccountDirectory accounts, Clock clock) {
        this.sessions = sessions;
        this.series = series;
        this.participation = participation;
        this.people = people;
        this.accounts = accounts;
        this.clock = clock;
    }
    public SessionSeriesPreview preview(SessionSeriesDraft draft) {
        ChildrenRules.text(draft.name(), 120);
        if (draft.recurrence() == null) {
            throw ChildrenException.invalid("Choose a recurrence pattern.");
        }
        MinistrySession source = sessions.ministrySession(draft.sourceSessionId())
            .orElseThrow(() -> ChildrenException.missing("Source session"));
        ChildrenRules.version(source.version(), draft.sourceVersion());
        Room room = sessions.room(source.roomId()).orElseThrow(() -> ChildrenException.missing("Room"));
        Location location =
            sessions.location(room.locationId()).orElseThrow(() -> ChildrenException.missing("Location"));
        MinistryClass group =
            participation.ministryClass(source.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
        Programme programme =
            participation.programme(group.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        if (source.seatLimit() > room.capacity()) {
            throw ChildrenException.invalid("Session seats exceed room capacity.");
        }
        List<SessionStaff> team = sessions.staff(source.id());
        Map<UUID, PersonDirectory.Entry> names = people.getAll(team.stream().map(SessionStaff::workerId).toList())
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        Map<UUID, Worker> workers = team.stream()
            .map(member -> sessions.worker(member.workerId()).orElseThrow(() -> ChildrenException.missing("Worker")))
            .collect(Collectors.toMap(Worker::personId, Function.identity()));
        Map<UUID, Boolean> active = workers.values()
            .stream()
            .collect(Collectors.toMap(Worker::personId, worker -> accounts.active(worker.accountId())));
        Instant now = clock.instant();
        ZoneId zone = ZoneId.of(location.timeZone());
        List<SessionSeriesPreview.Occurrence> occurrences = draft.recurrence().slots(zone).stream().map(slot -> {
            List<String> conflicts = new ArrayList<>();
            if (!slot.excluded()) {
                if (!slot.startsAt().isAfter(now)) {
                    conflicts.add("Choose a future start time.");
                }
                if (series.conflicts(source.classId(), source.roomId(), slot.startsAt(), slot.endsAt())) {
                    conflicts.add("The class or room is already booked for this time.");
                }
            }
            List<String> warnings = new ArrayList<>();
            int eligible = 0;
            boolean leader = false;
            for (SessionStaff member : team) {
                Worker worker = workers.get(member.workerId());
                PersonDirectory.Entry person = names.get(member.workerId());
                if (!worker.eligible(now, slot.endsAt()) || !active.get(member.workerId()) || person == null
                    || !person.available() || person.birthDate() == null
                    || person.birthDate().plusYears(18).isAfter(slot.date())) {
                    warnings.add((person == null ? "Team member" : person.name())
                        + ": review identity, account or screening/training before this session.");
                } else {
                    eligible++;
                    leader = leader || member.role() == StaffRole.LEADER;
                }
            }
            int capacity = CapacityPolicy.capacity(source.seatLimit(), room.capacity(), eligible, leader,
                programme.minStaff(), programme.childrenPerStaff());
            if (capacity < source.seatLimit()) {
                warnings.add("The copied team currently supports up to " + capacity + " of " + source.seatLimit()
                    + " planned seats once present. Review staffing before opening.");
            }
            if (team.stream().noneMatch(member -> member.role() == StaffRole.LEADER)) {
                warnings.add("Assign a leader before opening this session.");
            }
            return new SessionSeriesPreview.Occurrence(slot, List.copyOf(conflicts), List.copyOf(warnings));
        }).toList();
        return new SessionSeriesPreview(group.name(), room.name(), location.timeZone(), source.seatLimit(),
            team.stream()
                .map(member -> new SessionSeriesPreview.TeamMember(member.workerId(),
                    names.containsKey(member.workerId()) ? names.get(member.workerId()).name() : "Team member",
                    member.role().name()))
                .toList(),
            occurrences, now);
    }
}
