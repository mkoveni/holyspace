package org.onechurch.communities.application;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.communities.domain.GroupMeeting;
import org.onechurch.communities.domain.Membership;
import org.onechurch.communities.domain.repository.CommunityRepository;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.Set;
import java.util.UUID;
public final class GroupMeetings {
    private final CommunityReader reader;
    private final CommunityWriter writer;
    private final CommunityRepository repository;
    private final CommunityCalendar calendar;
    private final CommunityAccess access;
    private final PersonDirectory people;
    private final UnitOfWork tx;
    private final Clock clock;
    public GroupMeetings(CommunityReader reader, CommunityWriter writer, CommunityRepository repository,
        CommunityCalendar calendar, CommunityAccess access, PersonDirectory people, UnitOfWork tx, Clock clock) {
        this.reader = reader;
        this.writer = writer;
        this.repository = repository;
        this.calendar = calendar;
        this.access = access;
        this.people = people;
        this.tx = tx;
        this.clock = clock;
    }
    public CommunityCalendar.Preview preview(UUID community, CommunityCalendar.Schedule schedule) {
        access.require(community);
        return calendar.preview(schedule);
    }
    public UUID schedule(UUID actor, UUID request, UUID community, long version, CommunityCalendar.Schedule schedule,
        String hash) {
        String requestHash = CommunityHash.of(actor, "SCHEDULE", community, version, schedule, hash);
        return tx.execute(() -> {
            Community c = reader.community(community).orElseThrow(CommunityException::missing);
            access.manager(actor);
            Optional<CommunityReader.Request> old = reader.request(request);
            if (old.isPresent()) {
                if (!old.orElseThrow().hash().equals(requestHash)) {
                    throw CommunityException.conflict("This request identifier was already used.");
                }
                return old.orElseThrow().target();
            }
            CommunityRules.version(c.version(), version);
            if (!c.active() || !c.kind().equals("GROUP")) {
                throw CommunityException.conflict("Schedule meetings for an active group.");
            }
            List<CommunityCalendar.Date> dates = calendar.create(actor, request, c.name(), schedule, hash);
            c = repository.lockCommunity(community).orElseThrow(CommunityException::missing);
            access.manager(actor);
            Optional<CommunityReader.Request> repeated = reader.request(request);
            if (repeated.isPresent()) {
                if (!repeated.orElseThrow().hash().equals(requestHash)) {
                    throw CommunityException.conflict("This request identifier was already used.");
                }
                return repeated.orElseThrow().target();
            }
            CommunityRules.version(c.version(), version);
            if (!c.active()) {
                throw CommunityException.conflict("This group is archived.");
            }
            if (dates.isEmpty()) {
                throw CommunityException
                    .conflict("The schedule did not produce any group meetings. Review the dates again.");
            }
            UUID first = null;
            for (CommunityCalendar.Date date : dates) {
                GroupMeeting meeting =
                    new GroupMeeting(UUID.randomUUID(), community, date.id(), date.startsAt(), "OPEN", false, 0);
                repository.insert(meeting);
                if (first == null) {
                    first = meeting.id();
                }
            }
            repository.save(c.update(c.name(), c.description(), c.parentId(), c.campus(), c.active()));
            writer.request(request, requestHash, first);
            writer.audit(community, first, "MEETINGS_SCHEDULED", "Scheduled " + dates.size() + " meetings.", actor,
                clock.instant());
            return first;
        });
    }
    private GroupMeeting before(UUID id) {
        return reader.meeting(id).orElseThrow(CommunityException::missing);
    }
    private CommunityCalendar.Date lockSource(GroupMeeting m) {
        return calendar.lock(m.occurrenceId()).orElseThrow(CommunityException::missing);
    }
    private GroupMeeting lock(UUID id, UUID community, UUID actor) {
        repository.lockCommunity(community).orElseThrow(CommunityException::missing);
        access.actor(actor);
        access.require(community);
        return repository.lockMeeting(id).orElseThrow(CommunityException::missing);
    }
    private void open(GroupMeeting m, CommunityCalendar.Date date) {
        if (!m.status().equals("OPEN") || !date.status().equals("SCHEDULED")
            || !access.community(m.communityId()).active()) {
            throw CommunityException.conflict("This meeting is closed to new attendance.");
        }
    }
    private GroupMeeting freeze(GroupMeeting m, CommunityCalendar.Date date) {
        if (m.rosterFrozen()) {
            return m;
        }
        List<Membership> members = reader.membersAt(m.communityId(), date.startsAt(), 5001);
        if (members.size() > 5000) {
            throw CommunityException.conflict("A meeting roster can contain at most 5,000 members.");
        }
        members.forEach(member -> writer.roster(m.id(), member.personId(), member.id(), false));
        GroupMeeting frozen = m.change(m.status(), true);
        repository.save(frozen);
        return frozen;
    }
    public void prepare(UUID actor, UUID id, long version) {
        tx.execute(() -> {
            GroupMeeting before = before(id);
            CommunityCalendar.Date date = lockSource(before);
            GroupMeeting m = lock(id, before.communityId(), actor);
            CommunityRules.version(m.version(), version);
            open(m, date);
            if (clock.instant().isBefore(date.startsAt().minusSeconds(7200))) {
                throw CommunityException.conflict("Prepare the meeting roster from two hours before it starts.");
            }
            freeze(m, date);
            writer.audit(m.communityId(), id, "ROSTER_PREPARED", "Membership roster frozen for this gathering.", actor,
                clock.instant());
            return null;
        });
    }
    public void guest(UUID actor, UUID id, long version, UUID person, String reason) {
        String note = CommunityRules.text(reason, 2000);
        tx.execute(() -> {
            people.lock(person).filter(PersonDirectory.Entry::available).orElseThrow(CommunityException::missing);
            GroupMeeting before = before(id);
            CommunityCalendar.Date date = lockSource(before);
            GroupMeeting m = lock(id, before.communityId(), actor);
            CommunityRules.version(m.version(), version);
            open(m, date);
            if (!m.rosterFrozen()) {
                throw CommunityException.conflict("Prepare the member roster before adding guests.");
            }
            if (reader.rosterCount(id) >= 5000) {
                throw CommunityException.conflict("This meeting roster is full.");
            }
            writer.roster(id, person, null, true);
            repository.save(m.change(m.status(), true));
            writer.audit(m.communityId(), id, "GUEST_ADDED", note, actor, clock.instant());
            return null;
        });
    }
    public void attendance(UUID actor, UUID rowId, long version, String status, String reason) {
        String note = CommunityRules.text(reason, 2000);
        if (!Set.of("PRESENT", "ABSENT", "EXCUSED", "UNRECORDED").contains(status)) {
            throw CommunityException.invalid("Choose an attendance status.");
        }
        tx.execute(() -> {
            CommunityReader.Roster before = reader.rosterRow(rowId).orElseThrow(CommunityException::missing);
            people.lock(before.personId()).orElseThrow(CommunityException::missing);
            GroupMeeting meeting = before(before.meetingId());
            CommunityCalendar.Date date = lockSource(meeting);
            GroupMeeting m = lock(meeting.id(), meeting.communityId(), actor);
            CommunityReader.Roster row = reader.rosterRow(rowId).orElseThrow();
            CommunityRules.version(row.version(), version);
            if (row.version() == 0) {
                open(m, date);
                if (clock.instant().isBefore(date.startsAt().minusSeconds(7200))) {
                    throw CommunityException.conflict("Record attendance from two hours before the meeting.");
                }
            }
            if (!row.status().equals(status)) {
                writer.attendance(rowId, status);
                writer.audit(m.communityId(), rowId, "ATTENDANCE_" + status, note, actor, clock.instant());
            }
            return null;
        });
    }
    public void finish(UUID actor, UUID id, long version, String status, String reason) {
        String note = CommunityRules.text(reason, 2000);
        if (!Set.of("CANCELLED", "CLOSED").contains(status)) {
            throw CommunityException.invalid("Choose cancel or close.");
        }
        tx.execute(() -> {
            GroupMeeting before = before(id);
            CommunityCalendar.Date date = lockSource(before);
            GroupMeeting m = lock(id, before.communityId(), actor);
            CommunityRules.version(m.version(), version);
            if (!m.status().equals("OPEN")) {
                throw CommunityException.conflict("This meeting has already been closed or cancelled.");
            }
            if (status.equals("CANCELLED")) {
                access.manager(actor);
            }
            if (status.equals("CLOSED") && (clock.instant().isBefore(date.endsAt()) || !m.rosterFrozen())) {
                throw CommunityException
                    .conflict("Prepare and review attendance, then close the meeting after it ends.");
            }
            repository.save(m.change(status, m.rosterFrozen()));
            writer.audit(m.communityId(), id, "MEETING_" + status, note, actor, clock.instant());
            return null;
        });
    }
    public UUID followup(UUID actor, UUID request, UUID community, UUID person, String note, LocalDate due) {
        String text = CommunityRules.text(note, 2000);
        if (due == null) {
            throw CommunityException.invalid("Choose a follow-up date.");
        }
        String hash = CommunityHash.of(actor, "FOLLOWUP", community, person, text, due);
        return tx.execute(() -> {
            people.lock(person).filter(PersonDirectory.Entry::available).orElseThrow(CommunityException::missing);
            repository.lockCommunity(community).orElseThrow(CommunityException::missing);
            access.actor(actor);
            access.require(community);
            Optional<CommunityReader.Request> old = reader.request(request);
            if (old.isPresent()) {
                if (!old.orElseThrow().hash().equals(hash)) {
                    throw CommunityException.conflict("This request identifier was already used.");
                }
                return old.orElseThrow().target();
            }
            if (!access.community(community).active()
                || reader.currentMembership(community, person, clock.instant()).isEmpty()) {
                throw CommunityException.conflict("Follow-up requires a current member of an active community.");
            }
            UUID id = UUID.randomUUID();
            writer.followup(id, community, person, text, due, actor, clock.instant());
            writer.request(request, hash, id);
            writer.audit(community, id, "FOLLOWUP_CREATED", "Member follow-up recorded.", actor, clock.instant());
            return id;
        });
    }
    public void complete(UUID actor, UUID id, long version, String reason) {
        String note = CommunityRules.text(reason, 2000);
        tx.execute(() -> {
            CommunityReader.FollowUp before = reader.followup(id).orElseThrow(CommunityException::missing);
            repository.lockCommunity(before.communityId()).orElseThrow(CommunityException::missing);
            access.actor(actor);
            access.require(before.communityId());
            CommunityReader.FollowUp current = reader.followup(id).orElseThrow();
            CommunityRules.version(current.version(), version);
            if (!current.status().equals("OPEN")) {
                throw CommunityException.conflict("This follow-up is already complete.");
            }
            writer.completeFollowup(id);
            writer.audit(before.communityId(), id, "FOLLOWUP_COMPLETED", note, actor, clock.instant());
            return null;
        });
    }
}
