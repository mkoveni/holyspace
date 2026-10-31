package org.onechurch.communities.application;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.communities.domain.GroupMeeting;
import org.onechurch.communities.domain.Leadership;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.util.List;
import java.util.Map;
import java.util.Objects;
import java.util.Set;
import java.util.UUID;
import java.util.stream.Collectors;
public final class CommunityViews {
    public record Details(Community community, boolean canOperate) {}
    public record Directory(List<Community> communities, long total) {}
    public record Members(List<CommunityReader.Member> members, long total) {}
    public record Appointment(Leadership leadership, String accountName, String personName) {}
    public record Meeting(GroupMeeting meeting, CommunityCalendar.Date date, CommunityReader.Report counts) {}
    public record Meetings(List<Meeting> meetings, long total) {}
    public record Roster(List<CommunityReader.Roster> roster, long total, CommunityReader.Report counts) {}
    private final CommunityReader reader;
    private final CommunityAccess access;
    private final CommunityCalendar calendar;
    private final AccountNames accounts;
    private final Clock clock;
    private final PersonDirectory people;
    public CommunityViews(CommunityReader reader, CommunityAccess access, CommunityCalendar calendar,
        AccountNames accounts, Clock clock, PersonDirectory people) {
        this.reader = reader;
        this.access = access;
        this.calendar = calendar;
        this.accounts = accounts;
        this.clock = clock;
        this.people = people;
    }
    public Directory directory(String kind, String search, boolean archived, int limit, int offset) {
        CommunityRules.page(limit, offset);
        if (kind != null && !Set.of("MINISTRY", "GROUP").contains(kind)) {
            throw CommunityException.invalid("Choose ministry or group.");
        }
        UUID scope = access.directoryScope();
        String term = CommunityRules.optional(search, 150);
        return new Directory(reader.directory(kind, term, archived, scope, limit, offset, clock.instant()),
            reader.directoryCount(kind, term, archived, scope, clock.instant()));
    }
    public Details detail(UUID id) {
        if (access.directoryScope() != null) {
            access.require(id);
        }
        return new Details(access.community(id), access.canOperate(id));
    }
    public Members members(UUID id, boolean ended, int limit, int offset) {
        access.require(id);
        CommunityRules.page(limit, offset);
        return new Members(reader.members(id, ended, limit, offset), reader.memberCount(id, ended));
    }
    public List<Appointment> leadership(UUID id, int limit, int offset) {
        access.require(id);
        CommunityRules.page(limit, offset);
        List<Leadership> page = reader.leadership(id, limit, offset);
        Map<UUID, String> names =
            accounts.names(page.stream().map(Leadership::accountId).filter(Objects::nonNull).distinct().toList());
        Map<UUID, UUID> persons = page.stream()
            .collect(Collectors.toMap(Leadership::membershipId,
                l -> reader.membership(l.membershipId()).orElseThrow().personId(), (a, c) -> a));
        Map<UUID, String> personNames = people.getAll(persons.values())
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return page.stream()
            .map(l -> new Appointment(l,
                l.accountId() == null ? "No workspace login" : names.getOrDefault(l.accountId(), "Former account"),
                personNames.getOrDefault(persons.get(l.membershipId()), "Unavailable person")))
            .toList();
    }
    public Meetings meetings(UUID id, int limit, int offset) {
        access.require(id);
        CommunityRules.page(limit, offset);
        List<GroupMeeting> page = reader.meetings(id, limit, offset);
        Map<UUID, CommunityReader.Report> counts = reader.reports(page.stream().map(GroupMeeting::id).toList())
            .stream()
            .collect(Collectors.toMap(CommunityReader.Report::meetingId, r -> r));
        return new Meetings(page.stream()
            .map(m -> new Meeting(m, calendar.get(m.occurrenceId()).orElseThrow(CommunityException::missing),
                counts.get(m.id())))
            .toList(), reader.meetingCount(id));
    }
    public Roster roster(UUID id, int limit, int offset) {
        GroupMeeting m = reader.meeting(id).orElseThrow(CommunityException::missing);
        access.require(m.communityId());
        CommunityRules.page(limit, offset);
        return new Roster(reader.roster(id, limit, offset), reader.rosterCount(id),
            reader.reports(List.of(id)).getFirst());
    }
    public List<CommunityReader.History> history(UUID id, int limit, int offset) {
        access.require(id);
        CommunityRules.page(limit, offset);
        return reader.history(id, limit, offset);
    }
    public List<CommunityReader.FollowUp> followups(UUID id, boolean completed, int limit, int offset) {
        access.require(id);
        CommunityRules.page(limit, offset);
        return reader.followups(id, completed, limit, offset);
    }
}
